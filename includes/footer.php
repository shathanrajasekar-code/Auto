<?php
/**
 * Namma AutoParts - Global Footer
 */
?>
    </main> <!-- End Main Content Wrapper -->

    <footer class="bg-navy text-white pt-5 pb-3 mt-auto border-top border-secondary">
        <div class="container">
            <div class="row g-4">
                <!-- Branding and tagline -->
                <div class="col-md-4">
                    <h5 class="fw-bold mb-3 text-orange"><i class="fa fa-cogs me-1"></i>VeloParts</h5>
                    <p class="text-white-50 small">
                        <?php echo __('tagline'); ?>. Chennai, Coimbatore, Theni and across Tamil Nadu. Providing reliable vehicle compatibility, core returns, and partnered garage installation.
                    </p>
                    <div class="d-flex gap-3 fs-5 mt-3 text-white-50">
                        <a href="#" class="text-white-50 hover-orange"><i class="fab fa-facebook"></i></a>
                        <a href="#" class="text-white-50 hover-orange"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white-50 hover-orange"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-white-50 hover-orange"><i class="fab fa-whatsapp"></i></a>
                    </div>
                </div>

                <!-- Navigation Links -->
                <div class="col-md-2 col-6">
                    <h6 class="fw-bold text-white mb-3">Quick Links</h6>
                    <ul class="list-unstyled mb-0 text-xs">
                        <li class="mb-2"><a href="index.php" class="text-white-50 text-decoration-none hover-orange">Home</a></li>
                        <li class="mb-2"><a href="shop.php" class="text-white-50 text-decoration-none hover-orange">Catalog</a></li>
                        <li class="mb-2"><a href="diagram.php" class="text-white-50 text-decoration-none hover-orange">Interactive Blueprint</a></li>
                        <li class="mb-2"><a href="my-account.php" class="text-white-50 text-decoration-none hover-orange">My Garage</a></li>
                    </ul>
                </div>

                <div class="col-md-2 col-6">
                    <h6 class="fw-bold text-white mb-3">Support</h6>
                    <ul class="list-unstyled mb-0 text-xs">
                        <li class="mb-2"><a href="claims.php" class="text-white-50 text-decoration-none hover-orange">Fitment Claims</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none hover-orange">FAQ</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none hover-orange">B2B Terms</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none hover-orange">Privacy Policy</a></li>
                    </ul>
                </div>

                <!-- Contact details -->
                <div class="col-md-4">
                    <h6 class="fw-bold text-white mb-3">Contact Support</h6>
                    <p class="small text-white-50 mb-1"><i class="fa fa-envelope text-orange me-2"></i>support@veloparts.com</p>
                    <p class="small text-white-50 mb-1"><i class="fa fa-phone text-orange me-2"></i>+91 98765 43210</p>
                    <p class="small text-white-50"><i class="fa fa-map-marker-alt text-orange me-2"></i>Gandhipuram, Coimbatore, TN, India</p>
                </div>
            </div>
            
            <hr class="border-secondary my-4">

            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start">
                    <p class="small text-white-50 mb-0">&copy; <?php echo date('Y'); ?> VeloParts. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-center text-md-end mt-2 mt-md-0">
                    <span class="small text-white-50">GSTIN: 33VLPARTS1234Z</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap 5 Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Custom Global Javascript -->
    <script>
        $(document).ready(function() {
            // Live Search Autocomplete Functionality
            var searchInput = $('#liveSearchInput');
            var resultsBox = $('#liveSearchResults');

            searchInput.on('input', function() {
                var query = $(this).val().trim();
                
                if (query.length < 2) {
                    resultsBox.hide().empty();
                    return;
                }

                $.ajax({
                    url: '<?php echo SITE_URL; ?>/api/search.php',
                    type: 'GET',
                    data: { query: query },
                    dataType: 'json',
                    success: function(data) {
                        resultsBox.empty();
                        
                        if (data && data.length > 0) {
                            $.each(data, function(index, item) {
                                // Fallback image if not exists
                                var imagePath = '<?php echo SITE_URL; ?>/assets/uploads/' + (item.images && item.images.length > 0 ? JSON.parse(item.images)[0] : 'no-image.jpg');
                                var badgeHtml = '';
                                if (item.condition === 'refurbished') {
                                    badgeHtml = '<span class="badge bg-info ms-2" style="font-size:10px;">Refurbished</span>';
                                } else if (item.condition === 'used') {
                                    badgeHtml = '<span class="badge bg-warning ms-2" style="font-size:10px;">Used</span>';
                                }
                                
                                var itemHtml = `
                                    <a href="<?php echo SITE_URL; ?>/product-details.php?slug=${item.slug}" class="list-group-item list-group-item-action d-flex align-items-center gap-3 py-2">
                                        <div style="width: 40px; height: 40px; background: #eee;" class="rounded overflow-hidden flex-shrink-0 d-flex align-items-center justify-content-center">
                                            <i class="fa fa-cogs text-secondary"></i>
                                        </div>
                                        <div class="flex-grow-1 overflow-hidden">
                                            <div class="fw-bold text-dark text-truncate mb-0" style="font-size: 13px;">${item.name} ${badgeHtml}</div>
                                            <small class="text-muted" style="font-size: 11px;">SKU: ${item.sku} | Brand: ${item.brand}</small>
                                        </div>
                                        <div class="text-orange fw-bold flex-shrink-0 text-end" style="font-size: 13px; color:#f75d00;">₹${parseFloat(item.price).toFixed(2)}</div>
                                    </a>
                                `;
                                resultsBox.append(itemHtml);
                            });
                            resultsBox.show();
                        } else {
                            resultsBox.html('<div class="list-group-item text-muted small">No spare parts found.</div>').show();
                        }
                    },
                    error: function() {
                        resultsBox.hide();
                    }
                });
            });

            // Hide results box when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#navSearchForm').length) {
                    resultsBox.hide();
                }
            });
        });
    </script>
</body>
</html>
