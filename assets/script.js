jQuery(document).ready(function($) {
    let currentPage = 1;
    let isLoading = false;
    let searchTimeout;

    function checkAjaxVariables() {
        if (typeof llm_ajax === 'undefined') {
            console.error('LLM Ajax variables not loaded');
            return false;
        }
        return true;
    }

    $('.llm-copy-btn').on('click', function() {
        const btn = $(this);
        const promptText = btn.data('prompt');
        const originalText = btn.html();
        
        navigator.clipboard.writeText(promptText).then(function() {
            btn.addClass('copied');
            btn.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"></path></svg>Copied!');
            
            setTimeout(function() {
                btn.removeClass('copied');
                btn.html(originalText);
            }, 2000);
        }).catch(function() {
            const textarea = document.createElement('textarea');
            textarea.value = promptText;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
            
            btn.addClass('copied');
            btn.html('<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"></path></svg>Copied!');
            
            setTimeout(function() {
                btn.removeClass('copied');
                btn.html(originalText);
            }, 2000);
        });
    });
    
    function filterPrompts(resetPage = true) {
        if (isLoading || !checkAjaxVariables()) return;
        
        if (resetPage) {
            currentPage = 1;
        }
        
        isLoading = true;
        
        const data = {
            action: 'filter_prompts',
            library: $('#llm-library-filter').val(),
            search: $('#llm-search-input').val(),
            sort: $('#llm-sort-filter').val(),
            video_only: $('#llm-video-filter').is(':checked'),
            topics: $('.llm-topic-filter:checked').map(function() { return this.value; }).get(),
            tags: $('.llm-tag-filter:checked').map(function() { return this.value; }).get(),
            page: currentPage,
            nonce: llm_ajax.nonce
        };
        
        $.ajax({
            url: llm_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    if (resetPage) {
                        $('#llm-prompts-feed').html(response.data.html);
                    } else {
                        $('#llm-prompts-feed').append(response.data.html);
                    }
                    
                    if (response.data.found_posts === 0) {
                        $('#llm-no-results').show();
                        $('#llm-load-more-container').hide();
                    } else {
                        $('#llm-no-results').hide();
                        
                        if (response.data.current_page >= response.data.max_num_pages) {
                            $('#llm-load-more-container').hide();
                        } else {
                            $('#llm-load-more-container').show();
                        }
                    }
                }
                isLoading = false;
            },
            error: function() {
                isLoading = false;
            }
        });
    }
    
    function loadMorePrompts() {
        if (isLoading || !checkAjaxVariables()) return;
        
        currentPage++;
        isLoading = true;
        
        const data = {
            action: 'load_more_prompts',
            library: $('#llm-library-filter').val(),
            search: $('#llm-search-input').val(),
            sort: $('#llm-sort-filter').val(),
            video_only: $('#llm-video-filter').is(':checked'),
            topics: $('.llm-topic-filter:checked').map(function() { return this.value; }).get(),
            tags: $('.llm-tag-filter:checked').map(function() { return this.value; }).get(),
            page: currentPage,
            nonce: llm_ajax.nonce
        };
        
        $.ajax({
            url: llm_ajax.ajax_url,
            type: 'POST',
            data: data,
            success: function(response) {
                if (response.success) {
                    $('#llm-prompts-feed').append(response.data.html);
                    
                    if (response.data.current_page >= response.data.max_num_pages) {
                        $('#llm-load-more-container').hide();
                    }
                }
                isLoading = false;
            },
            error: function() {
                isLoading = false;
            }
        });
    }
    
    $('#llm-library-filter, #llm-sort-filter').on('change', function() {
        filterPrompts();
    });
    
    $('#llm-video-filter').on('change', function() {
        filterPrompts();
    });
    
    $('.llm-topic-filter, .llm-tag-filter').on('change', function() {
        filterPrompts();
    });
    
    $('#llm-search-input').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            filterPrompts();
        }, 300);
    });
    
    $('#llm-load-more').on('click', function() {
        loadMorePrompts();
    });
    
    $(document).on('change', '.llm-topic-filter, .llm-tag-filter', function() {
        filterPrompts();
    });
    
    function initializeScrollableContainers() {
        const topicsContainer = $('#llm-topics-container');
        const tagsContainer = $('#llm-tags-container');
        
        if (topicsContainer.find('.llm-checkbox-label').length > 5) {
            topicsContainer.addClass('scrollable');
        }
        
        if (tagsContainer.find('.llm-checkbox-label').length > 5) {
            tagsContainer.addClass('scrollable');
        }
    }
    
    initializeScrollableContainers();
});