jQuery(document).ready(function($) {
    let currentPage = 1;
    let isLoading = false;
    let searchTimeout;

    // Initialize collapsible elements
    function initializeCollapsibles() {
        // Use event delegation to handle dynamically loaded elements
        $(document).on('click', '.llm-filter-element .llm-filter-header', function(e) {
            // Don't toggle if clicking on radio button, input, or label
            if ($(e.target).is('input') || $(e.target).closest('label').length > 0) {
                e.stopPropagation();
                return;
            }
            
            e.preventDefault();
            const element = $(this).closest('.llm-filter-element');
            
            // Toggle the expanded class
            element.toggleClass('expanded');
        });
        
        // Handle checkbox clicks separately 
        $(document).on('click', '#llm-video-filter', function(e) {
            e.stopPropagation();
        });
        
    }

    // Initialize search overlay
    function initializeSearchOverlay() {
        $('#llm-search-trigger').on('click', function(e) {
            e.preventDefault();
            const searchOverlay = $('#llm-search-overlay');
            
            if (searchOverlay.hasClass('show')) {
                // Close search
                searchOverlay.removeClass('show');
            } else {
                // Open search
                searchOverlay.addClass('show');
                setTimeout(function() {
                    $('#llm-search-input').focus();
                }, 100);
            }
        });

        // Handle search input
        $('#llm-search-overlay #llm-search-input').on('input', function() {
            const searchTerm = $(this).val();
            // Update the actual filter and trigger search
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                filterPrompts();
            }, 300);
        });

        // Close on Enter key
        $('#llm-search-overlay #llm-search-input').on('keypress', function(e) {
            if (e.which === 13) {
                $('#llm-search-overlay').removeClass('show');
            }
        });

        // Close search when clicking outside sidebar
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.llm-sidebar').length) {
                $('#llm-search-overlay').removeClass('show');
            }
        });
    }

    // Initialize pagination
    function initializePagination() {
        $(document).on('click', '.llm-pagination-arrow:not(.disabled)', function(e) {
            e.preventDefault();
            const isNext = $(this).attr('id') === 'llm-next-page';
            
            if (isNext) {
                currentPage++;
            } else {
                currentPage = Math.max(1, currentPage - 1);
            }
            
            filterPrompts(false); // Don't reset page since we're navigating
            updatePaginationUI();
        });

        $(document).on('click', '.llm-pagination-number', function(e) {
            e.preventDefault();
            const page = parseInt($(this).data('page'));
            if (page !== currentPage && !isNaN(page)) {
                currentPage = page;
                filterPrompts(false); // Don't reset page since we're navigating
                updatePaginationUI();
            }
        });
    }

    function updatePaginationNumbers(maxPages) {
        const numbersContainer = $('#llm-pagination-numbers');
        numbersContainer.empty();
        
        // Always show first page
        numbersContainer.append(`<a href="#" class="llm-pagination-number" data-page="1">1</a>`);
        
        if (maxPages > 1) {
            // Show pages around current page
            let startPage = Math.max(2, currentPage - 1);
            let endPage = Math.min(maxPages - 1, currentPage + 1);
            
            // Add dots if needed
            if (startPage > 2) {
                numbersContainer.append('<span class="llm-pagination-dots">...</span>');
            }
            
            // Add middle pages
            for (let i = startPage; i <= endPage; i++) {
                if (i !== 1 && i !== maxPages) {
                    numbersContainer.append(`<a href="#" class="llm-pagination-number" data-page="${i}">${i}</a>`);
                }
            }
            
            // Add dots if needed
            if (endPage < maxPages - 1) {
                numbersContainer.append('<span class="llm-pagination-dots">...</span>');
            }
            
            // Always show last page if different from first
            if (maxPages > 1) {
                numbersContainer.append(`<a href="#" class="llm-pagination-number" data-page="${maxPages}">${maxPages}</a>`);
            }
        }
    }

    function updatePaginationUI() {
        // Remove active class from all numbers
        $('.llm-pagination-number').removeClass('active');
        
        // Add active class to current page
        $(`.llm-pagination-number[data-page="${currentPage}"]`).addClass('active');
        
        // Update arrow states
        if (currentPage === 1) {
            $('#llm-prev-page').addClass('disabled');
        } else {
            $('#llm-prev-page').removeClass('disabled');
        }
        
        // Get max pages from pagination numbers
        const maxPages = parseInt($('#llm-pagination-numbers .llm-pagination-number').last().data('page')) || 1;
        if (currentPage >= maxPages) {
            $('#llm-next-page').addClass('disabled');
        } else {
            $('#llm-next-page').removeClass('disabled');
        }
    }

    // Initialize all new functionality
    initializeCollapsibles();
    initializeSearchOverlay();
    initializePagination();

    function checkAjaxVariables() {
        if (typeof llm_ajax === 'undefined') {
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
        
        // Get the current library selection, prioritizing the selected filter over URL parameter
        let currentLibrary = $('#llm-library-filter').val();
        if (!currentLibrary && llm_ajax.selected_library) {
            currentLibrary = llm_ajax.selected_library;
            $('#llm-library-filter').val(llm_ajax.selected_library);
        }
        
        const data = {
            action: 'filter_prompts',
            library: currentLibrary,
            search: $('#llm-search-overlay #llm-search-input').val() || '',
            sort: 'newest', // Default sort for new design
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
                    // Always replace content for pagination, never append
                    $('#llm-prompts-feed').html(response.data.html);
                    
                    if (response.data.found_posts === 0) {
                        $('#llm-no-results').show();
                        $('#llm-pagination').hide();
                    } else {
                        $('#llm-no-results').hide();
                        $('#llm-pagination').show();
                        updatePaginationNumbers(response.data.max_num_pages);
                        updatePaginationUI();
                    }
                }
                isLoading = false;
            },
            error: function() {
                isLoading = false;
            }
        });
    }
    
    
    $('#llm-library-filter').on('change', function() {
        // Check if selected library is premium
        const selectedValue = $(this).val();
        const selectedOption = $(this).find('option:selected');
        const isPremium = selectedOption.data('premium') === 1;
        const libraryName = selectedOption.text().replace(' 🔒', ''); // Remove lock emoji for header
        
        if (selectedValue && isPremium) {
            showPremiumOverlay();
            return;
        } else {
            hidePremiumOverlay();
        }
        
        // Update header dynamically
        updateHeaderAndUrl(selectedValue, libraryName);
        
        filterPrompts();
    });
    
    function updateHeaderAndUrl(libraryId, libraryName) {
        // Update the header h1
        if (libraryId && libraryName) {
            $('.llm-header h1').text(libraryName);
        } else {
            $('.llm-header h1').text('LIBRERIA DIGITALE');
        }
        
        // Update the URL without page reload
        if (libraryId && llm_ajax.library_slugs && llm_ajax.library_slugs[libraryId]) {
            const slug = llm_ajax.library_slugs[libraryId];
            const newUrl = new URL(window.location);
            newUrl.searchParams.set('library', slug);
            window.history.pushState({}, '', newUrl);
        } else {
            // Remove library parameter from URL
            const newUrl = new URL(window.location);
            newUrl.searchParams.delete('library');
            window.history.pushState({}, '', newUrl);
        }
    }
    
    $('#llm-video-filter').on('change', function() {
        filterPrompts();
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
    
    // Premium library functions
    function showPremiumOverlay() {
        $('#llm-prompts-feed').hide();
        $('#llm-pagination').hide();
        $('#llm-no-results').hide();
        $('#llm-premium-overlay').show();
    }
    
    function hidePremiumOverlay() {
        $('#llm-premium-overlay').hide();
        $('#llm-prompts-feed').show();
        $('#llm-pagination').show();
    }
    
    // Back to dashboard button
    $('#llm-back-to-dashboard').on('click', function() {
        // Hide the premium overlay
        hidePremiumOverlay();
        
        // Get default library from localized script
        const defaultLibrary = llm_ajax.default_library;
        
        // Set library filter to default library or empty if no default
        if (defaultLibrary) {
            $('#llm-library-filter').val(defaultLibrary).trigger('change');
        } else {
            $('#llm-library-filter').val('').trigger('change');
        }
        
        // Remove library parameter from URL if it exists
        const url = new URL(window.location);
        if (url.searchParams.has('library')) {
            url.searchParams.delete('library');
            window.history.replaceState({}, document.title, url.toString());
        }
    });

    // Mobile Menu Toggle Functionality
    function initializeMobileMenu() {
        // Toggle mobile menu
        $(document).on('click', '.llm-hamburger', function(e) {
            e.preventDefault();
            $('.llm-mobile-menu').addClass('active');
            $('body').css('overflow', 'hidden'); // Prevent background scrolling
        });

        // Close mobile menu
        $(document).on('click', '.llm-mobile-close', function(e) {
            e.preventDefault();
            $('.llm-mobile-menu').removeClass('active');
            $('body').css('overflow', 'auto'); // Restore scrolling
        });

        // Close menu when clicking on a menu item
        $(document).on('click', '.llm-mobile-menu-nav a', function() {
            $('.llm-mobile-menu').removeClass('active');
            $('body').css('overflow', 'auto');
        });

        // Close menu when clicking outside of it
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.llm-mobile-menu, .llm-hamburger').length) {
                if ($('.llm-mobile-menu').hasClass('active')) {
                    $('.llm-mobile-menu').removeClass('active');
                    $('body').css('overflow', 'auto');
                }
            }
        });

        // Handle escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('.llm-mobile-menu').hasClass('active')) {
                $('.llm-mobile-menu').removeClass('active');
                $('body').css('overflow', 'auto');
            }
        });
    }

    // Initialize mobile menu
    initializeMobileMenu();

    // Global logout function
    window.llmLogout = function() {
        $.post(llm_ajax.ajax_url, {
            action: 'llm_logout'
        }).always(function() {
            // Simple redirect to clean page
            window.location.href = window.location.pathname;
        });
    };

    // Global password toggle function
    window.togglePassword = function(fieldId) {
        const passwordInput = fieldId ? document.getElementById(fieldId) : document.getElementById('user_password');
        
        if (!passwordInput) return;
        
        // Find the toggle button that was clicked by looking at the parent wrapper
        const wrapper = passwordInput.closest('.llm-password-wrapper');
        if (!wrapper) return;
        
        const toggle = wrapper.querySelector('.llm-password-toggle');
        if (!toggle) return;
        
        const eyeOpen = toggle.querySelector('.eye-open');
        const eyeClosed = toggle.querySelector('.eye-closed');
        
        if (passwordInput && eyeOpen && eyeClosed) {
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                eyeOpen.style.display = 'none';
                eyeClosed.style.display = 'block';
            } else {
                passwordInput.type = 'password';
                eyeOpen.style.display = 'block';
                eyeClosed.style.display = 'none';
            }
            
            // Keep focus on input after toggle
            passwordInput.focus();
        }
    };

});