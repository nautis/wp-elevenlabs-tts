(function ($) {
    "use strict";

    /* GO TO TOP BUTTON */
    $("#tessera-gototop").on('click', function (e) {
        e.preventDefault();
        $("html, body").animate({
            scrollTop: 0
        }, 500);
        return false;
    });

    /* SCROLL */
    $(".page-scroll").on('click', function (e) {
        e.preventDefault();
        var id = $(this).attr('href');
        var $id = $(id);
        if ($id.length === 0) {
            return;
        }
        $('html, body').animate({
            scrollTop: $id.offset().top - 30
        }, 500);
        return false;
    });

    $("body").find(".page-scroll").on('click', function (e) {
        var id = $(this).attr('href');
        var $id = $(id);
        if ($id.length === 0) {
            return;
        }
        $('html, body').animate({
            scrollTop: $id.offset().top - 30
        }, 500);
        return false;
    });

    /* EVENTS */
    $(document).ready(function () {
        $('body').find('[data-toggle="tooltip"]').tooltip();
    });
})(jQuery);

function openNav() {
    document.getElementById("sidenav").style.width = "300px";
}
  
function closeNav() {
    document.getElementById("sidenav").style.width = "0";
}