(function($) {
    "use strict";

    /* Temporary styles */
    $('#epiza').prepend('<style id="epiza-temp-styles"></style>');

    /* Tabs */
    $('#epiza').on('click','.epiza-tabs-menu li',function(){
        var target = $(this).data('target');
        var wrapper = $(this).parent().parent();
        wrapper.find('> .epiza-tab').removeClass('active');
        $(target).addClass('active');
        wrapper.find('> .epiza-tabs-menu li').removeClass('active');
        $(this).addClass('active');
    });

    /* Populate Movies */
    $('#epiza').find('#epiza-movies-search').on('click', function () {
        var keyword = $('#epiza').find('#epiza-movies-search-input').val();
        var data = {
            'action': 'epizaSearchMovies',
            'nonce': epizaParams.nonce,
            'keyword': keyword,
            'page': '1'
        };
        $.ajax({
            url : epizaParams.ajaxurl,
            data : data,
            type : 'POST',
            beforeSend: function ( xhr ) {
                $('#epiza').find('#epiza-movies').css('pointer-events', 'none');
                $('#epiza').find('#epiza-movies').css('opacity', 0.5);
            },
            success: function(data){
                if(data) {
                    $('#epiza').find('#epiza-movies-output').html(data);
                }
            },
            error: function(jqXHR,error, errorThrown) {
                if($("#epiza-bulk-import-error").length == 0) {
                    $('#epiza-bulk-import-table-wrap').before('<div id="epiza-bulk-import-error" class="notice notice-error is-dismissible"><button type="button" class="notice-dismiss"></button></div>');
                }
                $('#epiza-bulk-import-error').append('<p>' + error + '</p>');
            }
        }).done(function() {
            $('#epiza').find('#epiza-movies').css('pointer-events', 'auto');
            $('#epiza').find('#epiza-movies').css('opacity', 1);
        });
    });

    $('#epiza').on('click','#epiza-movies-loadmore',function(){
        var keyword = $('#epiza').find('#epiza-movies-search-input').val();
        var data = {
            'action': 'epizaSearchMovies',
            'nonce': epizaParams.nonce,
            'keyword': keyword,
            'page': parseInt($(this).data('page')) + 1
        };
        $.ajax({
            url : epizaParams.ajaxurl,
            data : data,
            type : 'POST',
            beforeSend: function ( xhr ) {
                $('#epiza').find('#epiza-movies').css('pointer-events', 'none');
                $('#epiza').find('#epiza-movies').css('opacity', 0.5);
                $('#epiza').find('#epiza-movies-loadmore').html(epizaParams.loading);
            },
            success: function(data){
                if(data) {
                    $('#epiza').find('#epiza-movies-loadmore').remove();
                    $('#epiza').find('#epiza-movies-output > .epiza-grid:last-child').after(data);
                } else {
                    $('#epiza').find('#epiza-movies-loadmore').prop('disabled', false);
                }
            },
            error: function(jqXHR,error, errorThrown) {
                if($("#epiza-bulk-import-error").length == 0) {
                    $('#epiza-bulk-import-table-wrap').before('<div id="epiza-bulk-import-error" class="notice notice-error is-dismissible"><button type="button" class="notice-dismiss"></button></div>');
                }
                $('#epiza-bulk-import-error').append('<p>' + error + '</p>');
            }
        }).done(function() {
            $('#epiza').find('#epiza-movies').css('pointer-events', 'auto');
            $('#epiza').find('#epiza-movies').css('opacity', 1);
            $('#epiza').find('#epiza-movies-loadmore').html(epizaParams.loadmore);
        });
    });

    /* Populate TV Shows */
    $('#epiza').find('#epiza-tv-search').on('click', function () {
        var keyword = $('#epiza').find('#epiza-tv-search-input').val();
        var data = {
            'action': 'epizaSearchTV',
            'nonce': epizaParams.nonce,
            'keyword': keyword,
            'page': '1'
        };
        $.ajax({
            url : epizaParams.ajaxurl,
            data : data,
            type : 'POST',
            beforeSend: function ( xhr ) {
                $('#epiza').find('#epiza-tv').css('pointer-events', 'none');
                $('#epiza').find('#epiza-tv').css('opacity', 0.5);
            },
            success: function(data){
                if(data) {
                    $('#epiza').find('#epiza-tv-output').html(data);
                }
            },
            error: function(jqXHR,error, errorThrown) {
                if($("#epiza-bulk-import-error").length == 0) {
                    $('#epiza-bulk-import-table-wrap').before('<div id="epiza-bulk-import-error" class="notice notice-error is-dismissible"><button type="button" class="notice-dismiss"></button></div>');
                }
                $('#epiza-bulk-import-error').append('<p>' + error + '</p>');
            }
        }).done(function() {
            $('#epiza').find('#epiza-tv').css('pointer-events', 'auto');
            $('#epiza').find('#epiza-tv').css('opacity', 1);
        });
    });

    $('#epiza').on('click','#epiza-tv-loadmore',function(){
        var keyword = $('#epiza').find('#epiza-tv-search-input').val();
        var data = {
            'action': 'epizaSearchTV',
            'nonce': epizaParams.nonce,
            'keyword': keyword,
            'page': parseInt($(this).data('page')) + 1
        };
        $.ajax({
            url : epizaParams.ajaxurl,
            data : data,
            type : 'POST',
            beforeSend: function ( xhr ) {
                $('#epiza').find('#epiza-tv').css('pointer-events', 'none');
                $('#epiza').find('#epiza-tv').css('opacity', 0.5);
                $('#epiza').find('#epiza-tv-loadmore').html(epizaParams.loading);
            },
            success: function(data){
                if(data) {
                    $('#epiza').find('#epiza-tv-loadmore').remove();
                    $('#epiza').find('#epiza-tv-output > .epiza-grid:last-child').after(data);
                } else {
                    $('#epiza').find('#epiza-tv-loadmore').prop('disabled', false);
                }
            },
            error: function(jqXHR,error, errorThrown) {
                if($("#epiza-bulk-import-error").length == 0) {
                    $('#epiza-bulk-import-table-wrap').before('<div id="epiza-bulk-import-error" class="notice notice-error is-dismissible"><button type="button" class="notice-dismiss"></button></div>');
                }
                $('#epiza-bulk-import-error').append('<p>' + error + '</p>');
            }
        }).done(function() {
            $('#epiza').find('#epiza-tv').css('pointer-events', 'auto');
            $('#epiza').find('#epiza-tv').css('opacity', 1);
            $('#epiza').find('#epiza-tv-loadmore').html(epizaParams.loadmore);
        });
    });

    /* LocalStorage */
    var STORAGE_KEY = 'epizaList';
    var getItemsFromStorage = () => {
        const storedItems = localStorage.getItem(STORAGE_KEY);
        return storedItems ? JSON.parse(storedItems) : [];
    };
    var saveItemsToStorage = (items) => {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(items));
    };
    var renderItems = () => {
        const items = getItemsFromStorage();
        const length = items.length;

        $('#epiza-import-count').html(length);
        $('#epiza-bulk-import-tbody').empty();
        $('#epiza-temp-styles').empty();

        if (length === 0) {
            $('#epiza-bulk-import-notice').show();
            $('#epiza-bulk-import-table-wrap').hide();
        } else {
            $('#epiza-bulk-import-notice').hide();
            $('#epiza-bulk-import-table-wrap').show();
        }

        items.forEach(([id, title]) => {
            const itemHtml = '<tr id="' + id + '"><th scope="row" class="check-column"><input type="checkbox" name="post[]" value="' + id + '"> </th> <td class="epiza-table-title" data-colname="Title">' + title + '</td> <td data-colname="Action" class="epiza-table-actions"> <a class="epiza-single-import" href="#" data-id="' + id + '"><strong>' + epizaParams.import + '</strong></a><span>|</span><a class="epiza-single-remove" href="#" data-id="' + id + '"><strong>' + epizaParams.remove + '</strong></a> </td> </tr>';
            const itemCss = '.epiza-masonry-item.' + id + ' .epiza-add-btn {display:none;}.epiza-masonry-item.' + id + ' .epiza-remove-btn {display:flex;}';
            $('#epiza-bulk-import-tbody').prepend(itemHtml);
            $('#epiza-temp-styles').prepend(itemCss);
        });
    };
    renderItems();

    /* Add to queue */
    $('#epiza').on('click','.epiza-add-btn',function(){
        var id = $(this).attr('data-id');
        var title = $(this).parent().parent().find('.epiza-masonry-item-title').html();
        var badge = epizaParams.movie;
        if (id.startsWith("t-")) {
            badge = epizaParams.tv;
        }
        $(this).css('display', 'none');
        $(this).parent().find('.epiza-remove-btn').css('display', 'flex');
        var items = getItemsFromStorage();
        var savedTitle = '<span class="epiza-table-badge">' + badge + '</span>' + title;
        var newItem = [id, savedTitle];
        items.push(newItem);
        saveItemsToStorage(items);
        renderItems();
    }); 

    /* Remove from the queue */
    $('#epiza').on('click','.epiza-remove-btn',function(){
        var idToDelete = $(this).attr('data-id');
        $(this).css('display', 'none');
        $(this).parent().find('.epiza-add-btn').css('display', 'flex');
        var items = getItemsFromStorage();
        items = items.filter(([id, title]) => id !== idToDelete);
        saveItemsToStorage(items);
        renderItems();
    }); 

    $('#epiza').on('click','.epiza-single-remove',function(){
        var idToDelete = $(this).attr('data-id');
        var items = getItemsFromStorage();
        items = items.filter(([id, title]) => id !== idToDelete);
        saveItemsToStorage(items);
        renderItems();
    }); 

    /* Bulk Import & Remove */
    $('#epiza').find('#epiza-action-top-submit').on('click', function () {
        var action = $('#epiza-action-top').find(':selected').val();
        var items = getItemsFromStorage();
        if (action == 'remove') {
            const checkedItems = [];
            $('#epiza-bulk-import-tbody').find('input[type="checkbox"]').each(function() {
                if ($(this).is(":checked")) {
                    const id = $(this).val();
                    checkedItems.push(id);
                }
            });
            if (checkedItems == '') {
                alert(epizaParams.action);
                return;
            }
            $.each(checkedItems, function( index, val ) {
                items = items.filter(([id, title]) => id !== val);
            });
            saveItemsToStorage(items);
            renderItems();
        } else if (action == 'import') {
            const checkedItems = [];
            $('#epiza-bulk-import-tbody').find('input[type="checkbox"]').each(function() {
                if ($(this).is(":checked")) {
                    const id = $(this).val();
                    checkedItems.push(id);
                }
            });
            if (checkedItems == '') {
                alert(epizaParams.action);
                return;
            }
            $('#epiza-bulk-import-table-wrap').css('pointer-events', 'none');
            $('#epiza-action-top-submit').prop('disabled', true);
            $('#epiza-bulk-import-table-wrap').before('<div id="epiza-bulk-import-started" class="notice notice-info"><p>' + epizaParams.started + ' <span></span></p></div>');
            const totalCount = checkedItems.length;
            bulkImport(checkedItems, totalCount);
        } else {
            alert(epizaParams.action);
            return;
        }
    });

    var currentIndex = 0;
    var importError = false;

    function bulkImport(checkedItems, totalCount) {
        if (importError) {
            importError = false;
            return;
        }
        if (currentIndex >= totalCount) {
            $('#epiza-bulk-import-started').remove();
            if ($("#epiza-bulk-import-success").length == 0) {
                $('#epiza-bulk-import-table-wrap').before('<div id="epiza-bulk-import-success" class="notice notice-success is-dismissible"><button type="button" class="notice-dismiss"></button></div>');
            }
            $('#epiza-bulk-import-success').append('<p>' + epizaParams.done + '</p>');
            currentIndex = 0;
            $('#epiza-bulk-import-table-wrap').css('pointer-events', 'auto');
            $('#epiza-action-top-submit').prop('disabled', false);
            return;
        }
        
        var currentId = checkedItems[currentIndex];
        $('#' + currentId).find('.epiza-single-import strong').html(epizaParams.importing);
        $('#' + currentId).find('.epiza-single-import').addClass('epiza-loading-text');
        $('#epiza-bulk-import-started span').html(`(${currentIndex + 1} of ${totalCount}).`);

        var data = {
            'action': 'epizaImport',
            'nonce': epizaParams.nonce,
            'id': currentId
        };
        $.ajax({
            url : epizaParams.ajaxurl,
            data : data,
            type : 'POST',
            success: function (response) {
                if (response.success) {
                    $('#' + currentId).parent().find('.epiza-single-remove').trigger('click');
                } else {
                    if($("#epiza-bulk-import-error").length == 0) {
                        $('#epiza-bulk-import-table-wrap').before('<div id="epiza-bulk-import-error" class="notice notice-error is-dismissible"><button type="button" class="notice-dismiss"></button></div>');
                    }
                    const title = $('#' + currentId).find('.epiza-table-title').html();
                    $('#epiza-bulk-import-error').append('<p>Failed to create post for ' + title + '. Reason: ' + response.data.message + '</p>');
                    $('#' + currentId).find('.epiza-single-import strong').html(epizaParams.import);
                }
            },
            error: function (xhr, status, error) {
                if($("#epiza-bulk-import-error").length == 0) {
                    $('#epiza-bulk-import-table-wrap').before('<div id="epiza-bulk-import-error" class="notice notice-error is-dismissible"><button type="button" class="notice-dismiss"></button></div>');
                }
                $('#epiza-bulk-import-error').append('<p>' + error + '</p>');
                btn.find('strong').html(epizaParams.import);
                btn.removeClass('epiza-loading-text');
                importError = true;
            },
            complete: function () {
                currentIndex++;
                bulkImport(checkedItems, totalCount);
            }
        });
    }

    /* Single Import */
    $('#epiza').on('click','.epiza-single-import',function(){
        var btn = $(this);
        var id = btn.attr('data-id');
        var data = {
            'action': 'epizaImport',
            'nonce': epizaParams.nonce,
            'id': id
        };
        $.ajax({
            url : epizaParams.ajaxurl,
            data : data,
            type : 'POST',
            beforeSend: function ( xhr ) {
                $('#epiza').find('#epiza-bulk-import-table-wrap').css('pointer-events', 'none');
                btn.find('strong').html(epizaParams.importing);
                btn.addClass('epiza-loading-text');
            },
            success: function(response){
                if(response.success) {
                    btn.parent().find('.epiza-single-remove').trigger('click');
                } else {
                    if($("#epiza-bulk-import-error").length == 0) {
                        $('#epiza-bulk-import-table-wrap').before('<div id="epiza-bulk-import-error" class="notice notice-error is-dismissible"><button type="button" class="notice-dismiss"></button></div>');
                    }
                    $('#epiza-bulk-import-error').append('<p>' + response.data.message + '</p>');
                    btn.find('strong').html(epizaParams.import);
                }
            },
            error: function(jqXHR,error, errorThrown) {
                if($("#epiza-bulk-import-error").length == 0) {
                    $('#epiza-bulk-import-table-wrap').before('<div id="epiza-bulk-import-error" class="notice notice-error is-dismissible"><button type="button" class="notice-dismiss"></button></div>');
                }
                $('#epiza-bulk-import-error').append('<p>' + error + '</p>');
                btn.find('strong').html(epizaParams.import);
                btn.removeClass('epiza-loading-text');
            }
        }).done(function() {
            $('#epiza').find('#epiza-bulk-import-table-wrap').css('pointer-events', 'auto');
        });
    });

    /* Notice Dismiss Button */
    $('#epiza').on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeTo(100, 0, function() {
            $(this).slideUp(100, function() {
                $(this).remove();
            });
        });
    });
})(jQuery);