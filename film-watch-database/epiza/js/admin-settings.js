(function($) {
    "use strict";
    var $subtitle = $("#wpbody-content").find(".cmb-type-title");
    $subtitle.on("click", function () {
        $(this).find("h3").toggleClass('opened');
        $(this).nextUntil(".cmb-type-title").toggle();
    });
    $(document).ready(function() {
        $subtitle.nextUntil(".cmb-type-title").hide();
    });
    $('#movie_slug, #tv_slug, #movie_genre_slug, #tv_genre_slug, #actors_slug').bind('keyup blur', function() {
        if($(this).val().match(/[^A-Za-z0-9_-]/g)){
            alert('Invalid character usage. Only letters, numbers, dashes and underscores are allowed.');
            $(this).val($(this).val().replace(/[^A-Za-z0-9_-]/g, ''));
            return false;
        }                      
    });
})(jQuery);