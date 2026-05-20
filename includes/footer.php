</div>
    </main>

    <script>
        $(document).ready(function() {
            // Replace the existing logout link functionality
            $('a[href="../auth/logout.php"]').on('click', function(e) {
                e.preventDefault(); // Prevent default link behavior
                $('#logoutModal').modal('show'); // Show logout confirmation modal
            });
            
            // Also handle mobile menu logout if exists
            $('#mobileMenuLogout').on('click', function(e) {
                e.preventDefault();
                $('#logoutModal').modal('show');
            });
            
            // Optional: Session timeout handling
            let sessionTimeout;
            let warningTimeout;
            
            // Set session timeout (e.g., 30 minutes = 1800000 ms)
            const SESSION_DURATION = 30 * 60 * 1000; // 30 minutes
            const WARNING_BEFORE = 60 * 1000; // Warn 1 minute before
            
            function resetSessionTimer() {
                clearTimeout(sessionTimeout);
                clearTimeout(warningTimeout);
                
                // Set warning timeout
                warningTimeout = setTimeout(() => {
                    $('#sessionTimeoutModal').modal('show');
                    startCountdown();
                }, SESSION_DURATION - WARNING_BEFORE);
                
                // Set session timeout
                sessionTimeout = setTimeout(() => {
                    window.location.href = '../auth/logout.php?timeout=1';
                }, SESSION_DURATION);
            }
            
            function startCountdown() {
                let countdown = 60;
                const countdownElement = document.getElementById('timeoutCountdown');
                
                const interval = setInterval(() => {
                    countdown--;
                    if (countdownElement) {
                        countdownElement.textContent = countdown;
                    }
                    
                    if (countdown <= 0) {
                        clearInterval(interval);
                    }
                }, 1000);
                
                // Store interval to clear it when modal is hidden
                $('#sessionTimeoutModal').on('hidden.bs.modal', function() {
                    clearInterval(interval);
                });
            }
            
            // Extend session function
            window.extendSession = function() {
                $.ajax({
                    url: '../auth/extend_session.php',
                    method: 'POST',
                    success: function() {
                        $('#sessionTimeoutModal').modal('hide');
                        resetSessionTimer();
                        showToast('Session extended successfully!', 'success');
                    },
                    error: function() {
                        showToast('Failed to extend session. Please try again.', 'error');
                    }
                });
            };
            
            // Reset timer on user activity
            $(document).on('mousemove keydown click', function() {
                resetSessionTimer();
            });
            
            // Initial timer start
            resetSessionTimer();
            
            // Add keyboard shortcut (Ctrl + Q) for quick logout
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.key === 'q') {
                    e.preventDefault();
                    $('#logoutModal').modal('show');
                }
            });

            // Add smooth logout animation
            $('#logoutModal .btn-danger').on('click', function(e) {
                e.preventDefault();
                const logoutUrl = $(this).attr('href');
                
                // Add loading state
                $(this).html('<i class="fas fa-spinner fa-spin me-2"></i>Logging out...').prop('disabled', true);
                
                // Simulate slight delay for better UX
                setTimeout(() => {
                    window.location.href = logoutUrl;
                }, 500);
            });
            
            // Sidebar Toggle Functionality
            const sidebar = document.getElementById('sidebar');
            const mainContent = document.getElementById('mainContent');
            const toggleBtn = document.getElementById('sidebarToggle');
            const mobileOverlay = document.getElementById('mobileOverlay');
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            
            // Check localStorage for sidebar state
            const sidebarCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (sidebarCollapsed) {
                sidebar.classList.add('collapsed');
                mainContent.classList.add('expanded');
                toggleBtn.classList.add('collapsed');
            }
            
            // Toggle sidebar on button click
            if (toggleBtn) {
                toggleBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('collapsed');
                    mainContent.classList.toggle('expanded');
                    this.classList.toggle('collapsed');
                    
                    // Save state to localStorage
                    localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
                });
            }
            
            // Mobile menu toggle
            if (mobileMenuToggle) {
                mobileMenuToggle.addEventListener('click', function() {
                    sidebar.classList.add('show');
                    mobileOverlay.classList.add('show');
                });
            }
            
            // Close mobile menu when clicking overlay
            if (mobileOverlay) {
                mobileOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    mobileOverlay.classList.remove('show');
                });
            }
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
            
            // Initialize popovers
            var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
            popoverTriggerList.map(function(popoverTriggerEl) {
                return new bootstrap.Popover(popoverTriggerEl);
            });
            
            // DataTable initialization
            if ($('.data-table').length) {
                $('.data-table').DataTable({
                    "pageLength": 10,
                    "responsive": true,
                    "language": {
                        "search": "<i class='fas fa-search'></i> Search:",
                        "lengthMenu": "Show _MENU_ entries",
                        "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                        "infoEmpty": "Showing 0 to 0 of 0 entries",
                        "infoFiltered": "(filtered from _MAX_ total entries)",
                        "paginate": {
                            "first": "<i class='fas fa-angle-double-left'></i>",
                            "last": "<i class='fas fa-angle-double-right'></i>",
                            "next": "<i class='fas fa-angle-right'></i>",
                            "previous": "<i class='fas fa-angle-left'></i>"
                        }
                    },
                    "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>t<"row"<"col-sm-12 col-md-5"i><"col-sm-12 col-md-7"p>>',
                    "initComplete": function() {
                        $('.dataTables_filter input').addClass('form-control form-control-sm');
                        $('.dataTables_length select').addClass('form-select form-select-sm');
                    }
                });
            }
            
            // Page-specific initializations
            const pageInitializers = {
                'inventory_categories': 'initInventoryCategories',
                'location_types': 'initLocationTypes',
                'locations': 'initLocations',
                'inventory_rooms': 'initInventoryRooms',
                'all_equipment': 'initAllEquipment',
                'consumables': 'initConsumables'
            };
            
            for (let [page, initFn] of Object.entries(pageInitializers)) {
                if (window.location.href.includes(page) && typeof window[initFn] === 'function') {
                    window[initFn]();
                }
            }
            
            // Auto-hide alerts after 5 seconds
            setTimeout(function() {
                document.querySelectorAll('.alert:not(.alert-permanent)').forEach(alert => {
                    alert.style.transition = 'opacity 0.5s ease';
                    alert.style.opacity = '0';
                    setTimeout(() => alert.remove(), 500);
                });
            }, 5000);
        });
        
        // Fullscreen toggle function
        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen();
            } else {
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                }
            }
        }
        
        // Global confirmation dialog using SweetAlert
        window.confirmAction = function(title, text, icon = 'warning') {
            return Swal.fire({
                title: title,
                text: text,
                icon: icon,
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, proceed!',
                cancelButtonText: 'Cancel'
            });
        };
        
        // Global success message
        window.showSuccess = function(message) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: message,
                timer: 3000,
                showConfirmButton: false
            });
        };
        
        // Global error message
        window.showError = function(message) {
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: message
            });
        };
        
        // Global loading spinner
        window.showLoading = function() {
            Swal.fire({
                title: 'Loading...',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        };
        
        // Close loading spinner
        window.hideLoading = function() {
            Swal.close();
        };
        
        // Format currency
        window.formatCurrency = function(amount) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
                minimumFractionDigits: 2
            }).format(amount);
        };
        
        // Format date
        window.formatDate = function(dateString, format = 'MMMM D, YYYY') {
            return moment(dateString).format(format);
        };
        
        // Copy to clipboard
        window.copyToClipboard = function(text) {
            navigator.clipboard.writeText(text).then(() => {
                showSuccess('Copied to clipboard!');
            }).catch(() => {
                showError('Failed to copy');
            });
        };
        
        // Debounce function for search inputs
        window.debounce = function(func, wait) {
            let timeout;
            return function executedFunction(...args) {
                const later = () => {
                    clearTimeout(timeout);
                    func(...args);
                };
                clearTimeout(timeout);
                timeout = setTimeout(later, wait);
            };
        };

        // Toast notification function
        function showToast(message, type = 'success') {
            if (!$('#toastContainer').length) {
                $('body').append('<div id="toastContainer" style="position: fixed; top: 20px; right: 20px; z-index: 9999;"></div>');
            }
            
            const toastId = 'toast_' + Date.now();
            const bgColor = type === 'success' ? 'bg-success' : 'bg-danger';
            const icon = type === 'success' ? 'check-circle' : 'exclamation-circle';
            
            const toastHtml = `
                <div id="${toastId}" class="toast show align-items-center text-white ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true">
                    <div class="d-flex">
                        <div class="toast-body">
                            <i class="fas fa-${icon} me-2"></i>
                            ${message}
                        </div>
                        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                    </div>
                </div>
            `;
            
            $('#toastContainer').append(toastHtml);
            
            setTimeout(() => {
                $(`#${toastId}`).fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    </script>
</body>
</html>