<?php
/**
 * Namma AutoParts - Spare Parts Shop Catalog
 */
$page_title = "Browse Spare Parts";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';

// Fetch categories for sidebar filter
try {
    $cat_stmt = $pdo->query("SELECT * FROM categories WHERE parent_id IS NULL");
    $sidebar_categories = $cat_stmt->fetchAll();
} catch (PDOException $e) {
    $sidebar_categories = [];
}

// Fetch distinct brands for sidebar filter
try {
    $brand_stmt = $pdo->query("SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND brand != '' ORDER BY brand ASC");
    $sidebar_brands = $brand_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $sidebar_brands = [];
}

// Pre-fill values from URL if present
$url_search = isset($_GET['search']) ? clean_input($_GET['search']) : '';
$url_cat = isset($_GET['category']) ? clean_input($_GET['category']) : '';
$url_cond = isset($_GET['condition']) ? clean_input($_GET['condition']) : '';
$url_core = isset($_GET['core']) ? 1 : 0;
?>

<div class="container my-4">
    <div class="row">
        <!-- Sidebar Filters -->
        <div class="col-lg-3 col-md-4 mb-4">
            <div class="card shadow-sm border-light">
                <div class="card-header bg-navy text-white d-flex align-items-center justify-content-between">
                    <h5 class="mb-0 fs-6"><i class="fa fa-filter me-2"></i>Filters</h5>
                    <button class="btn btn-sm btn-outline-light py-0 px-2" id="clearFiltersBtn" style="font-size: 11px;">Reset</button>
                </div>
                <div class="card-body p-3">
                    
                    <!-- Search Input -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small">Search</label>
                        <div class="input-group">
                            <input type="text" class="form-control form-control-sm" id="filterSearch" value="<?php echo esc($url_search); ?>" placeholder="Part name, SKU, brand...">
                        </div>
                    </div>

                    <!-- Compatibility Fitment Toggle -->
                    <?php if (isset($_SESSION['active_vehicle'])): ?>
                        <div class="mb-4 bg-light p-2 rounded border border-success">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" role="switch" id="filterFitment" checked>
                                <label class="form-check-label fw-bold text-success small" for="filterFitment">
                                    <i class="fa fa-check-circle me-1"></i>My Garage Fitment
                                </label>
                            </div>
                            <span class="text-xs text-muted">Show only compatible parts for <strong><?php echo esc($_SESSION['active_vehicle']['model']); ?></strong>.</span>
                        </div>
                    <?php endif; ?>

                    <!-- Categories Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small">Category</label>
                        <div class="d-flex flex-column gap-2" style="max-height: 150px; overflow-y: auto;">
                            <?php foreach ($sidebar_categories as $cat): ?>
                                <div class="form-check">
                                    <input class="form-check-input category-checkbox" type="checkbox" value="<?php echo esc($cat['slug']); ?>" id="cat_<?php echo $cat['id']; ?>" <?php echo $url_cat === $cat['slug'] ? 'checked' : ''; ?>>
                                    <label class="form-check-label small" for="cat_<?php echo $cat['id']; ?>">
                                        <?php echo esc($cat['name']); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Brand Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small">Brand</label>
                        <div class="d-flex flex-column gap-2" style="max-height: 150px; overflow-y: auto;">
                            <?php foreach ($sidebar_brands as $index => $brand): ?>
                                <div class="form-check">
                                    <input class="form-check-input brand-checkbox" type="checkbox" value="<?php echo esc($brand); ?>" id="brand_<?php echo $index; ?>">
                                    <label class="form-check-label small" for="brand_<?php echo $index; ?>">
                                        <?php echo esc($brand); ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Condition Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small">Condition</label>
                        <div class="d-flex flex-column gap-2">
                            <div class="form-check">
                                <input class="form-check-input condition-checkbox" type="checkbox" value="new" id="cond_new" <?php echo $url_cond === 'new' ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="cond_new">New</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input condition-checkbox" type="checkbox" value="used" id="cond_used" <?php echo $url_cond === 'used' ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="cond_used">Used</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input condition-checkbox" type="checkbox" value="refurbished" id="cond_refurbished" <?php echo $url_cond === 'refurbished' ? 'checked' : ''; ?>>
                                <label class="form-check-label small" for="cond_refurbished">Refurbished</label>
                            </div>
                        </div>
                    </div>

                    <!-- Core Refundable Charge Filter -->
                    <div class="mb-4">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" value="1" id="filterCore" <?php echo $url_core === 1 ? 'checked' : ''; ?>>
                            <label class="form-check-label small fw-bold" for="filterCore">
                                <i class="fa fa-undo me-1 text-danger"></i>Has Core Charge
                            </label>
                        </div>
                        <span class="text-xs text-muted d-block mt-1">Parts eligible for a refund on old core exchange.</span>
                    </div>

                    <!-- Price Filter -->
                    <div>
                        <label class="form-label fw-bold text-dark small">Price Range (₹)</label>
                        <div class="row g-2">
                            <div class="col-6">
                                <input type="number" class="form-control form-control-sm" id="filterMinPrice" placeholder="Min">
                            </div>
                            <div class="col-6">
                                <input type="number" class="form-control form-control-sm" id="filterMaxPrice" placeholder="Max">
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <!-- Catalog Product Grid -->
        <div class="col-lg-9 col-md-8">
            <!-- Catalog Header -->
            <div class="d-flex justify-content-between align-items-center mb-3 bg-white p-3 rounded shadow-sm border border-light">
                <span class="text-muted small"><strong id="totalCountSpan">0</strong> parts found</span>
                
                <div class="d-flex align-items-center gap-2">
                    <label for="sortDropdown" class="text-muted small text-nowrap mb-0">Sort By</label>
                    <select class="form-select form-select-sm" id="sortDropdown" style="width: 150px;">
                        <option value="newest">Newest</option>
                        <option value="price_asc">Price: Low to High</option>
                        <option value="price_desc">Price: High to Low</option>
                        <option value="rating">Rating</option>
                    </select>
                </div>
            </div>

            <!-- Skeleton Loaders -->
            <div id="skeletonLoader" class="row g-3" style="display: none;">
                <?php for($i = 0; $i < 6; $i++): ?>
                    <div class="col-lg-4 col-sm-6 mb-3">
                        <div class="card h-100 placeholder-glow" style="border:1px solid #eee;">
                            <div class="placeholder col-12" style="height: 180px; background-color:#e2e8f0;"></div>
                            <div class="card-body">
                                <h5 class="card-title placeholder-glow">
                                    <span class="placeholder col-6"></span>
                                </h5>
                                <p class="card-text placeholder-glow">
                                    <span class="placeholder col-7"></span>
                                    <span class="placeholder col-4"></span>
                                    <span class="placeholder col-4"></span>
                                </p>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>

            <!-- Product Grid container -->
            <div id="productGrid" class="row g-3">
                <!-- Loaded Dynamically via AJAX -->
            </div>

            <!-- Pagination container -->
            <div id="paginationContainer" class="mt-4">
                <!-- Loaded Dynamically via AJAX -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initial fetch
    fetchProducts(1);

    // Event listeners
    $('.category-checkbox, .brand-checkbox, .condition-checkbox, #filterCore, #filterFitment').on('change', function() {
        fetchProducts(1);
    });

    $('#sortDropdown').on('change', function() {
        fetchProducts(1);
    });

    // Throttled price inputs
    var priceTimeout;
    $('#filterMinPrice, #filterMaxPrice').on('input', function() {
        clearTimeout(priceTimeout);
        priceTimeout = setTimeout(function() {
            fetchProducts(1);
        }, 600);
    });

    // Throttled search
    var searchTimeout;
    $('#filterSearch').on('input', function() {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(function() {
            fetchProducts(1);
        }, 500);
    });

    // Pagination links click handler
    $(document).on('click', '#paginationContainer .page-link', function(e) {
        e.preventDefault();
        var page = $(this).data('page');
        if (page) {
            fetchProducts(page);
        }
    });

    // Reset Filters button
    $('#clearFiltersBtn').on('click', function() {
        $('#filterSearch').val('');
        $('.category-checkbox').prop('checked', false);
        $('.brand-checkbox').prop('checked', false);
        $('.condition-checkbox').prop('checked', false);
        $('#filterCore').prop('checked', false);
        $('#filterMinPrice').val('');
        $('#filterMaxPrice').val('');
        if ($('#filterFitment').length > 0) {
            $('#filterFitment').prop('checked', true);
        }
        $('#sortDropdown').val('newest');
        fetchProducts(1);
    });

    function fetchProducts(page) {
        // Show Skeletons, Hide Product Grid
        $('#productGrid').hide().empty();
        $('#paginationContainer').hide();
        $('#skeletonLoader').show();

        // Collect inputs
        var searchVal = $('#filterSearch').val();
        var minPriceVal = $('#filterMinPrice').val();
        var maxPriceVal = $('#filterMaxPrice').val();
        var sortVal = $('#sortDropdown').val();
        var coreVal = $('#filterCore').is(':checked') ? 1 : 0;
        var fitmentVal = $('#filterFitment').length > 0 && $('#filterFitment').is(':checked') ? 1 : 0;

        var categoriesArr = [];
        $('.category-checkbox:checked').each(function() {
            categoriesArr.push($(this).val());
        });

        var brandsArr = [];
        $('.brand-checkbox:checked').each(function() {
            brandsArr.push($(this).val());
        });

        var conditionsArr = [];
        $('.condition-checkbox:checked').each(function() {
            conditionsArr.push($(this).val());
        });

        // AJAX Request
        $.ajax({
            url: 'api/filter.php',
            type: 'GET',
            data: {
                search: searchVal,
                categories: categoriesArr,
                brands: brandsArr,
                conditions: conditionsArr,
                min_price: minPriceVal,
                max_price: maxPriceVal,
                core_charge: coreVal,
                fitment_only: fitmentVal,
                sort: sortVal,
                page: page
            },
            dataType: 'json',
            success: function(response) {
                // Hide Skeletons
                $('#skeletonLoader').hide();
                
                if (response.success) {
                    $('#totalCountSpan').text(response.total_items);
                    
                    if (response.products && response.products.length > 0) {
                        $.each(response.products, function(index, p) {
                            var imagesArr = [];
                            try {
                                imagesArr = JSON.parse(p.images);
                            } catch(e) {}
                            
                            var imgFilename = (imagesArr && imagesArr.length > 0) ? imagesArr[0] : 'no-image.jpg';
                            
                            // Condition badge markup
                            var condBadge = '';
                            if (p.condition === 'new') {
                                condBadge = `<span class="badge bg-success badge-condition">New</span>`;
                            } else if (p.condition === 'used') {
                                condBadge = `<span class="badge bg-warning text-dark badge-condition">Used</span>`;
                            } else {
                                condBadge = `<span class="badge bg-info badge-condition">Refurbished</span>`;
                            }

                            // Core charge badge markup
                            var coreBadge = '';
                            if (parseFloat(p.core_charge) > 0) {
                                coreBadge = `<span class="badge bg-danger position-absolute" style="top:10px; right:10px; font-size:10px;">Core: +₹${parseFloat(p.core_charge).toFixed(0)}</span>`;
                            }

                            // Compatibility badge markup
                            var fitmentBadge = '';
                            if (response.active_vehicle) {
                                var isComp = response.fitment_map.indexOf(p.id) !== -1;
                                if (isComp) {
                                    fitmentBadge = `<span class="badge bg-success badge-compatibility"><i class="fa fa-check-circle me-1"></i>Fits your ${response.active_vehicle.model}</span>`;
                                } else {
                                    fitmentBadge = `<span class="badge bg-danger badge-compatibility"><i class="fa fa-times-circle me-1"></i>Does not fit</span>`;
                                }
                            }

                            // Price block markup
                            var priceBlock = '';
                            if (p.discount_price && parseFloat(p.discount_price) > 0) {
                                priceBlock = `
                                    <span class="fs-5 fw-bold text-orange">₹${parseFloat(p.discount_price).toFixed(2)}</span>
                                    <span class="text-xs text-muted text-decoration-line-through">₹${parseFloat(p.price).toFixed(2)}</span>
                                `;
                            } else {
                                priceBlock = `<span class="fs-5 fw-bold text-orange">₹${parseFloat(p.price).toFixed(2)}</span>`;
                            }

                            var productCardHtml = `
                                <div class="col-lg-4 col-sm-6 mb-3">
                                    <div class="product-card card h-100">
                                        <div class="card-img-container">
                                            <img src="assets/uploads/${imgFilename}" alt="${p.name}" onerror="this.src='https://placehold.co/200x200?text=Auto+Part'">
                                            ${condBadge}
                                            ${coreBadge}
                                            ${fitmentBadge}
                                        </div>
                                        <div class="card-body d-flex flex-column p-3">
                                            <small class="text-muted mb-1">${p.brand} | ${p.category_name}</small>
                                            <h6 class="card-title fw-bold text-truncate-2 mb-2" style="font-size: 14px; height: 38px; overflow: hidden; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;">
                                                <a href="product-details.php?slug=${p.slug}" class="text-decoration-none text-dark hover-orange">${p.name}</a>
                                            </h6>
                                            <div class="mt-auto d-flex align-items-center gap-2 mb-3">
                                                ${priceBlock}
                                            </div>
                                            <div class="row g-2">
                                                <div class="col-8">
                                                    <a href="product-details.php?slug=${p.slug}" class="btn btn-accent btn-sm w-100">
                                                        <i class="fa fa-eye me-1"></i>Details
                                                    </a>
                                                </div>
                                                <div class="col-4">
                                                    <a href="https://wa.me/${p.whatsapp_number ? p.whatsapp_number : '919876543210'}?text=Inquiry%20about%20${encodeURIComponent(p.name)}%20(SKU:%20${p.sku})" class="btn btn-success btn-sm w-100" target="_blank">
                                                        <i class="fab fa-whatsapp text-white"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            `;
                            $('#productGrid').append(productCardHtml);
                        });
                        
                        $('#productGrid').fadeIn();
                        $('#paginationContainer').html(response.pagination).show();
                    } else {
                        $('#productGrid').html('<div class="col-12 text-center py-5"><i class="fa fa-search fa-3x text-muted mb-3"></i><p class="text-muted">No spare parts match your filters. Try adjusting your search criteria.</p></div>').fadeIn();
                    }
                } else {
                    $('#productGrid').html(`<div class="col-12 alert alert-danger">Error filtering items: ${response.message}</div>`).fadeIn();
                }
            },
            error: function(xhr, status, error) {
                $('#skeletonLoader').hide();
                $('#productGrid').html(`<div class="col-12 alert alert-danger">AJAX Error: failed to contact filter engine.</div>`).fadeIn();
            }
        });
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
