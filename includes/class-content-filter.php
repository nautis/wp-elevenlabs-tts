<?php
/**
 * Content Filter Class
 * Filters post content to prepare it for text-to-speech conversion
 */

if (!defined('ABSPATH')) {
    exit;
}

class ElevenLabs_TTS_Content_Filter {

    /**
     * Filter post content for TTS
     *
     * @param string $content Post content
     * @return string Filtered content
     */
    public static function filter_content($content) {
        // Remove References section first (before other processing)
        $content = self::remove_references_section($content);

        // Remove shortcodes
        $content = strip_shortcodes($content);

        // Remove HTML comments
        $content = preg_replace('/<!--(.|\s)*?-->/', '', $content);

        // Remove script and style tags with their content
        $content = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $content);
        $content = preg_replace('/<style\b[^>]*>(.*?)<\/style>/is', '', $content);

        // Remove code blocks (pre and code tags)
        $content = preg_replace('/<pre\b[^>]*>(.*?)<\/pre>/is', '', $content);
        $content = preg_replace('/<code\b[^>]*>(.*?)<\/code>/is', '', $content);

        // Remove figure captions
        $content = preg_replace('/<figcaption\b[^>]*>(.*?)<\/figcaption>/is', '', $content);

        // Remove figure tags but keep alt text from images
        $content = preg_replace('/<figure\b[^>]*>/i', '', $content);
        $content = preg_replace('/<\/figure>/i', '', $content);

        // Extract alt text from images and replace images with alt text
        $content = preg_replace_callback('/<img[^>]+alt=["\']([^"\']*)["\'][^>]*>/i', function($matches) {
            return !empty($matches[1]) ? $matches[1] . '. ' : '';
        }, $content);

        // Remove remaining images without alt text
        $content = preg_replace('/<img[^>]*>/i', '', $content);

        // Remove iframes (embedded videos, etc.)
        $content = preg_replace('/<iframe\b[^>]*>(.*?)<\/iframe>/is', '', $content);

        // Remove tables (optional - you may want to keep table data)
        $content = preg_replace('/<table\b[^>]*>(.*?)<\/table>/is', '', $content);

        // Remove forms
        $content = preg_replace('/<form\b[^>]*>(.*?)<\/form>/is', '', $content);

        // Remove buttons
        $content = preg_replace('/<button\b[^>]*>(.*?)<\/button>/is', '', $content);

        // Convert headings to plain text with a period for natural pauses
        $content = preg_replace('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/is', '$1. ', $content);

        // Convert list items to plain text with better formatting
        $content = preg_replace('/<li[^>]*>(.*?)<\/li>/is', '$1. ', $content);
        $content = preg_replace('/<[uo]l[^>]*>/i', '', $content);
        $content = preg_replace('/<\/[uo]l>/i', ' ', $content);

        // Convert line breaks to spaces
        $content = preg_replace('/<br\s*\/?>/i', ' ', $content);

        // Convert paragraphs to text with periods
        $content = preg_replace('/<p[^>]*>(.*?)<\/p>/is', '$1. ', $content);

        // Convert divs and spans to plain text
        $content = preg_replace('/<div[^>]*>/i', '', $content);
        $content = preg_replace('/<\/div>/i', ' ', $content);
        $content = preg_replace('/<span[^>]*>/i', '', $content);
        $content = preg_replace('/<\/span>/i', '', $content);

        // Convert links to text with context
        $content = preg_replace('/<a[^>]+href=["\']([^"\']*)["\'][^>]*>(.*?)<\/a>/is', '$2', $content);

        // Remove any remaining HTML tags
        $content = strip_tags($content);

        // Decode HTML entities
        $content = html_entity_decode($content, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Apply watch-specific terminology fixes
        $content = self::apply_watch_terminology_fixes($content);

        // Clean up whitespace
        $content = preg_replace('/\s+/', ' ', $content);

        // Remove multiple periods
        $content = preg_replace('/\.{2,}/', '.', $content);

        // Remove spaces before punctuation
        $content = preg_replace('/\s+([.,!?;:])/', '$1', $content);

        // Trim
        $content = trim($content);

        // Apply character limit if needed (ElevenLabs has limits based on plan)
        // Default to 100,000 characters to be safe
        $max_chars = apply_filters('elevenlabs_tts_max_characters', 100000);
        if (strlen($content) > $max_chars) {
            $content = substr($content, 0, $max_chars);
            // Try to end at a sentence
            $last_period = strrpos($content, '.');
            if ($last_period !== false && $last_period > ($max_chars - 200)) {
                $content = substr($content, 0, $last_period + 1);
            }
        }

        return $content;
    }

    /**
     * Get post content for TTS
     *
     * @param int|WP_Post $post Post ID or object
     * @return string|WP_Error Filtered content or error
     */
    public static function get_post_content_for_tts($post) {
        $post = get_post($post);

        if (!$post) {
            return new WP_Error('invalid_post', 'Invalid post');
        }

        // Get post content
        $content = $post->post_content;

        // Apply WordPress content filters (but not 'the_content' to avoid recursion)
        $content = apply_filters('elevenlabs_tts_pre_filter_content', $content, $post);

        // Filter the content
        $content = self::filter_content($content);

        // Add title at the beginning
        $title = get_the_title($post);
        if (!empty($title)) {
            $content = $title . '. ' . $content;
        }

        // Allow other plugins to modify the content
        $content = apply_filters('elevenlabs_tts_filtered_content', $content, $post);

        return $content;
    }

    /**
     * Estimate audio duration
     *
     * @param string $text Text content
     * @return int Estimated duration in seconds
     */
    public static function estimate_duration($text) {
        // Average speaking rate is about 150 words per minute
        $word_count = str_word_count($text);
        $duration = ceil(($word_count / 150) * 60);

        return $duration;
    }

    /**
     * Count characters in text
     *
     * @param string $text Text content
     * @return int Character count
     */
    public static function count_characters($text) {
        return strlen($text);
    }

    /**
     * Remove References section from content
     * Removes everything after a heading containing "References"
     *
     * @param string $content HTML content
     * @return string Content without References section
     */
    private static function remove_references_section($content) {
        // Look for References heading (h2, h3, h4) and remove everything after it
        // This needs to be done before HTML is stripped
        $patterns = array(
            '/<h[2-4][^>]*>\s*References\s*<\/h[2-4]>.*$/is',  // Exact match
            '/<h[2-4][^>]*>[^<]*References[^<]*<\/h[2-4]>.*$/is'  // Contains "References"
        );

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $content = preg_replace($pattern, '', $content);
                break;
            }
        }

        return $content;
    }

    /**
     * Apply watch-specific terminology fixes for better pronunciation
     *
     * @param string $content Plain text content (after HTML removal)
     * @return string Content with pronunciation fixes applied
     */
    private static function apply_watch_terminology_fixes($content) {
        // Define replacements array with patterns and CMU Arpabet phoneme pronunciations
        // Using phoneme tags for Eleven Turbo v2 model
        $replacements = array(
            // Brand names (alphabetical order) - using CMU Arpabet phonemes
            '/\b(A\.\s*Lange\s*&\s*S(?:ö|o)hne)\b/i' => function($m) {
                return 'A. <phoneme alphabet="cmu-arpabet" ph="L AA1 NG AH0">Lange</phoneme> & <phoneme alphabet="cmu-arpabet" ph="Z OW1 N AH0">Söhne</phoneme>';
            },
            '/\b(Audemars)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="OW1 D AH0 M AA1 R">$1</phoneme>',
            '/\b(Piguet)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="P IY1 G EY1">$1</phoneme>',
            '/\b(Baume)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="B OW1 M">$1</phoneme>',
            '/\b(Mercier)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="M EH1 R S IY0 EY1">$1</phoneme>',
            '/\b(Blancpain)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="B L AO1 P AE1 N">$1</phoneme>',
            '/\b(Bovet)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="B UW1 V EY1">$1</phoneme>',
            '/\b(Breguet)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="B R EH1 G EY0">$1</phoneme>',
            '/\b(Breitling)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="B R AY1 T L IH0 NG">$1</phoneme>',
            '/\b(Bremont)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="B R EY1 M AA0 N">$1</phoneme>',
            '/\b(Bulgari)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="B UH0 L G AA1 R IY0">$1</phoneme>',
            '/\b(Carl\s*F\.?\s*B(?:u|ü)cherer)\b/i' => function($m) {
                return '<phoneme alphabet="cmu-arpabet" ph="K AA1 R L">Carl</phoneme> F. <phoneme alphabet="cmu-arpabet" ph="B UH1 K ER0 ER0">Bucherer</phoneme>';
            },
            '/\b(Cartier)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="K AA1 R T IY0 EY1">$1</phoneme>',
            '/\b(Chopard)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="SH OW1 P AA1 R">$1</phoneme>',
            '/\b(Cuervo)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="K W EH1 R V OW0">$1</phoneme>',
            '/\b(Sobrinos)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="S OW0 B R IY1 N OW0 S">$1</phoneme>',
            '/\b(Custos)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="K UW1 S T OW0 S">$1</phoneme>',
            '/\b(Cyma)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="S IY1 M AH0">$1</phoneme>',
            '/\b(Daniel\s+Roth)\b/i' => function($m) {
                return 'Daniel <phoneme alphabet="cmu-arpabet" ph="R OW1 T">Roth</phoneme>';
            },
            '/\b(De\s+Bethune)\b/i' => function($m) {
                return '<phoneme alphabet="cmu-arpabet" ph="D AH0">De</phoneme> <phoneme alphabet="cmu-arpabet" ph="B EH0 T UW1 N">Bethune</phoneme>';
            },
            '/\b(DeWitt)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="D AH0 V IH1 T">$1</phoneme>',
            '/\b(Doxa)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="D AA1 K S AH0">$1</phoneme>',
            '/\b(Dubey)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="D UW1 B EY0">$1</phoneme>',
            '/\b(Schaldenbrand)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="SH AE1 L D AH0 N B R AE0 N D">$1</phoneme>',
            '/\b(Ebel)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="IY1 B EH1 L">$1</phoneme>',
            '/\b(F\.?\s*P\.?\s*Journe)\b/i' => function($m) {
                return '<phoneme alphabet="cmu-arpabet" ph="EH1 F P IY1">F.P.</phoneme> <phoneme alphabet="cmu-arpabet" ph="ZH UW1 R N">Journe</phoneme>';
            },
            '/\b(Favre)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="F AA1 V">$1</phoneme>',
            '/\b(Leuba)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="L OY1 B AH0">$1</phoneme>',
            '/\b(Fr(?:é|e)d(?:é|e)rique)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="F R EH1 D ER0 IH0 K">Frédérique</phoneme>',
            '/\b(Constant)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="K AA1 N S T AH0 N T">$1</phoneme>',
            '/\b(Franck)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="F R AA1 NG K">$1</phoneme>',
            '/\b(Muller)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="M Y UW1 L ER0">$1</phoneme>',
            '/\b(G(?:é|e)rald)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="ZH EH1 R AA0 L D">Gérald</phoneme>',
            '/\b(Charles)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="SH AA1 R L">$1</phoneme>',
            '/\b(Genta)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="ZH EH1 N T AH0">$1</phoneme>',
            '/\b(Girard)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="ZH IH1 R AA1 R D">$1</phoneme>',
            '/\b(Perregaux)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="P EH1 R AH0 G OW1">$1</phoneme>',
            '/\b(Glash(?:ü|u)tte)\b/i' => '<phoneme alphabet="ipa" ph="ˈɡlaːsˌhʏtə">Glashütte</phoneme>',
            '/\b(Glycine)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="G L AY1 S IY0 N">$1</phoneme>',
            '/\b(Greubel)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="G R OY1 B AH0 L">$1</phoneme>',
            '/\b(Forsey)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="F AO1 R S IY0">$1</phoneme>',
            '/\b(Moser)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="M OW1 Z ER0">$1</phoneme>',
            '/\b(Hublot)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="UW1 B L OW0">$1</phoneme>',
            '/\b(IWC)\b/' => '<phoneme alphabet="cmu-arpabet" ph="AY1 D AH1 B AH0 L Y UW1 S IY1">IWC</phoneme>',
            '/\b(Schaffhausen)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="SH AE1 F HH AW1 Z AH0 N">$1</phoneme>',
            '/\b(Jaeger)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="Y EY1 G ER0">$1</phoneme>',
            '/\b(LeCoultre)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="L AH0 K UW1 L T R AH0">$1</phoneme>',
            '/\b(Longines)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="L AO1 N JH IY0 N">$1</phoneme>',
            '/\b(Louis)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="L UW1 IY0">$1</phoneme>',
            '/\b((?:É|E)rard)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="EY1 R AA1 R">Érard</phoneme>',
            '/\b(Moinet)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="M W AA1 N EY1">$1</phoneme>',
            '/\b(Vuitton)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="V W IY1 T AA1 N">$1</phoneme>',
            '/\b(Maurice)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="M AO1 R IH0 S">$1</phoneme>',
            '/\b(Lacroix)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="L AA1 K R W AA0">$1</phoneme>',
            '/\b(Mido)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="M IY1 D OW0">$1</phoneme>',
            '/\b(Montblanc)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="M AA1 N B L AA1 N">$1</phoneme>',
            '/\b(Movado)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="M OW1 V AA1 D OW0">$1</phoneme>',
            '/\b(M(?:ü|u)hle)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="M Y UW1 L AH0">Mühle</phoneme>',
            '/\b(Nomos)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="N OW1 M OW0 S">$1</phoneme>',
            '/\b(Officine)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="AO1 F IH0 CH IY0 N EY0">$1</phoneme>',
            '/\b(Panerai)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="P AA1 N EH0 R AY1">$1</phoneme>',
            '/\b(Parmigiani)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="P AA1 R M IH0 JH AA1 N IY0">$1</phoneme>',
            '/\b(Fleurier)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="F L ER1 IY0 EY0">$1</phoneme>',
            '/\b(Patek)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="P AA1 T EH0 K">$1</phoneme>',
            '/\b(Philippe)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="F IH1 L IH0 P">$1</phoneme>',
            '/\b(Perrelet)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="P EH1 R AH0 L EY0">$1</phoneme>',
            '/\b(Piaget)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="P IY1 AH0 ZH EY1">$1</phoneme>',
            '/\b(Ralph)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="R AE1 L F">$1</phoneme>',
            '/\b(Lauren)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="L AO1 R EH0 N">$1</phoneme>',
            '/\b(Richard)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="R IY1 SH AA1 R">$1</phoneme>',
            '/\b(Mille)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="M IY1 L">$1</phoneme>',
            '/\b(Roger)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="R OW1 ZH EY1">$1</phoneme>',
            '/\b(Dubuis)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="D UW1 B W IY0">$1</phoneme>',
            '/\b(Sinn)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="Z IH1 N">$1</phoneme>',
            '/\b(Heuer)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="HH OY1 ER0">$1</phoneme>',
            '/\b(Tissot)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="T IY1 S OW1">$1</phoneme>',
            '/\b(Tutima)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="T UW1 T IH0 M AH0">$1</phoneme>',
            '/\b(Ulysse)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="Y UW1 L IH0 S">$1</phoneme>',
            '/\b(Nardin)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="N AA1 R D IH0 N">$1</phoneme>',
            '/\b(Universal)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="Y UW2 N AH0 V ER1 S AH0 L">$1</phoneme>',
            '/\b(Gen(?:è|e)ve)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="JH EH0 N EH1 V">Genève</phoneme>',
            '/\b(Vacheron)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="V AE1 SH ER0 AO0 N">$1</phoneme>',
            '/\b(Constantin)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="K AA1 N S T AH0 N T IY1 N">$1</phoneme>',
            '/\b(Victorinox)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="V IH0 K T AO1 R IH0 N AA0 K S">$1</phoneme>',
            '/\b(Zenith)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="Z EH1 N IH0 TH">$1</phoneme>',

            // Common watch terms with CMU Arpabet phonemes
            '/(Guilloch(?:é|e))/iu' => '<phoneme alphabet="cmu-arpabet" ph="G IY1 OW0 SH EY1">$1</phoneme>',
            '/\b(Haute\s+horlogerie)\b/i' => function($m) {
                return '<phoneme alphabet="cmu-arpabet" ph="OW1 T">Haute</phoneme> <phoneme alphabet="cmu-arpabet" ph="AO1 R L OW1 ZH ER0 IY0">horlogerie</phoneme>';
            },
            '/\b(Horology)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="HH AO0 R AA1 L AH0 JH IY0">$1</phoneme>',
            '/\b(mare nostrum)\b/i' => function($m) {
                return '<phoneme alphabet="cmu-arpabet" ph="M AA1 R EY0">mare</phoneme> <phoneme alphabet="cmu-arpabet" ph="N AA1 S T R AH0 M">nostrum</phoneme>';
            },
            '/\bRef\.\s*/i' => 'Reference ',
            '/\b(Tourbillon)\b/i' => '<phoneme alphabet="cmu-arpabet" ph="T UH1 R B IH0 L AA0 N">$1</phoneme>',
            '/\b(ETA)\b/' => '<phoneme alphabet="cmu-arpabet" ph="IY1 T AH0">ETA</phoneme>',

            // Material codes
            '/\bAISI\s*316L\b/i' => 'A I S I three one six L',

            // PAM model numbers (e.g., PAM00716 → PAM oh oh seven one six)
            '/\bPAM(\d)(\d)(\d)(\d)(\d)\b/' => function($matches) {
                $digits = array('oh', 'one', 'two', 'three', 'four', 'five', 'six', 'seven', 'eight', 'nine');
                $spoken = 'PAM ';
                for ($i = 1; $i <= 5; $i++) {
                    $digit = (int)$matches[$i];
                    $spoken .= $digits[$digit] . ' ';
                }
                return trim($spoken);
            },

            // Measurements with mm (e.g., 42mm or 42 mm → forty-two millimeters)
            '/\b(\d+)\s*mm\b/i' => '$1 millimeters',

            // Roman numerals in caliber names (e.g., OP XXXIII → O P thirty-three)
            '/\b(OP|Calibre|Caliber)\s+([A-Z]{1,3})\s+(X{0,3})(IX|IV|V?I{0,3})\b/i' => function($matches) {
                $prefix = $matches[1];
                $letters = $matches[2];
                $roman = $matches[3] . $matches[4];

                // Pronounce Calibre/Caliber properly
                if (strtolower($prefix) === 'calibre' || strtolower($prefix) === 'caliber') {
                    $prefix = 'KAL-ih-ber';
                }

                // Convert letters to individual spoken letters
                $spoken_letters = implode(' ', str_split($letters));

                // Convert Roman numerals to number
                $roman_values = array('M' => 1000, 'CM' => 900, 'D' => 500, 'CD' => 400,
                                     'C' => 100, 'XC' => 90, 'L' => 50, 'XL' => 40,
                                     'X' => 10, 'IX' => 9, 'V' => 5, 'IV' => 4, 'I' => 1);
                $number = 0;
                $roman = strtoupper($roman);
                foreach ($roman_values as $key => $value) {
                    while (strpos($roman, $key) === 0) {
                        $number += $value;
                        $roman = substr($roman, strlen($key));
                    }
                }

                return $prefix . ' ' . $spoken_letters . ' ' . $number;
            },

            // General Caliber/Calibre pronunciation (for cases not caught by Roman numeral pattern)
            '/\bCalib(er|re)\b/i' => 'KAL-ih-ber'
        );

        // Apply all replacements
        foreach ($replacements as $pattern => $replacement) {
            if (is_callable($replacement)) {
                $content = preg_replace_callback($pattern, $replacement, $content);
            } else {
                $content = preg_replace($pattern, $replacement, $content);
            }
        }

        return $content;
    }
}
