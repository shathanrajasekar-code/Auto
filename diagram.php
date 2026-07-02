<?php
/**
 * Namma AutoParts - Interactive Exploded Parts Diagram (OEM BOM Catalog)
 */
$page_title = "Interactive Parts Diagram";
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/header.php';
?>

<style>
    .diagram-svg-container {
        position: relative;
        background-color: #0b1d33;
        border-radius: 12px;
        overflow: hidden;
        border: 2px solid #1e293b;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        height: 400px;
    }
    .hotspot {
        cursor: pointer;
        fill: #f75d00;
        fill-opacity: 0.35;
        stroke: #f75d00;
        stroke-width: 2;
        transition: all 0.2s ease-in-out;
    }
    .hotspot:hover, .hotspot.active {
        fill-opacity: 0.7;
        stroke: #fff;
        stroke-width: 3;
        r: 25 !important;
    }
    .hotspot-pulse {
        animation: pulse-ring 1.8s infinite;
        fill: #f75d00;
        fill-opacity: 0.1;
        pointer-events: none;
    }
    @keyframes pulse-ring {
        0% { transform: scale(0.95); opacity: 0.8; }
        50% { transform: scale(1.1); opacity: 0.4; }
        100% { transform: scale(0.95); opacity: 0.8; }
    }
    .diagram-tab-btn {
        font-weight: 600;
        border-radius: 30px;
    }
    .diagram-tab-btn.active {
        background-color: #f75d00 !important;
        border-color: #f75d00 !important;
        color: #fff !important;
    }
</style>

<div class="container my-4">
    <div class="text-center mb-4">
        <h2 class="fw-bold text-dark mb-1"><i class="fa fa-images text-orange me-2"></i>Interactive Parts Diagram</h2>
        <p class="text-muted">Click on the glowing hotspots of the systems below to instantly find compatible matching parts.</p>
        
        <!-- System Toggles -->
        <div class="d-inline-flex gap-2 p-1 bg-white border rounded-pill shadow-sm">
            <button class="btn btn-light diagram-tab-btn active" id="tabEngineBtn" data-target="engine-diagram">
                <i class="fa fa-car-battery me-1"></i>Engine System
            </button>
            <button class="btn btn-light diagram-tab-btn" id="tabBrakesBtn" data-target="brakes-diagram">
                <i class="fa fa-disc me-1"></i>Braking System
            </button>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-lg-10">
            <!-- 1. Engine Diagram -->
            <div id="engine-diagram" class="diagram-svg-container p-4 mb-4">
                <svg viewBox="0 0 800 400" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                    <!-- Exploded Engine Block Blueprint drawing using SVG lines -->
                    <rect x="150" y="80" width="500" height="240" rx="10" fill="none" stroke="#334155" stroke-width="2" stroke-dasharray="5,5"/>
                    <rect x="250" y="100" width="300" height="150" fill="none" stroke="#475569" stroke-width="3"/>
                    
                    <!-- Cylinder heads -->
                    <circle cx="280" cy="175" r="30" fill="none" stroke="#64748b" stroke-width="2"/>
                    <circle cx="350" cy="175" r="30" fill="none" stroke="#64748b" stroke-width="2"/>
                    <circle cx="420" cy="175" r="30" fill="none" stroke="#64748b" stroke-width="2"/>
                    <circle cx="490" cy="175" r="30" fill="none" stroke="#64748b" stroke-width="2"/>

                    <!-- Alternator pully assembly -->
                    <circle cx="580" cy="230" r="35" fill="none" stroke="#475569" stroke-width="3"/>
                    <circle cx="580" cy="230" r="15" fill="none" stroke="#64748b" stroke-width="2"/>
                    <line x1="530" y1="230" x2="580" y2="230" stroke="#475569" stroke-width="2"/>

                    <!-- Spark plug labels -->
                    <path d="M 280,100 L 280,145" stroke="#f75d00" stroke-width="2" stroke-dasharray="2,2"/>
                    <text x="280" y="90" fill="#cbd5e1" font-size="12" text-anchor="middle">Spark Plugs</text>

                    <!-- Fuel Injector lines -->
                    <path d="M 380,110 L 380,145" stroke="#f75d00" stroke-width="2" stroke-dasharray="2,2"/>
                    <text x="380" y="90" fill="#cbd5e1" font-size="12" text-anchor="middle">Injectors</text>

                    <!-- Alternator label -->
                    <path d="M 580,170 L 580,195" stroke="#f75d00" stroke-width="2" stroke-dasharray="2,2"/>
                    <text x="580" y="160" fill="#cbd5e1" font-size="12" text-anchor="middle">Alternator</text>

                    <!-- Clickable Hotspot Circles -->
                    <!-- Spark Plugs (6) -->
                    <circle cx="280" cy="145" r="20" class="hotspot" data-slug="spark-plugs" title="Spark Plugs"/>
                    <circle cx="280" cy="145" r="15" class="hotspot-pulse"/>
                    
                    <!-- Fuel Injectors (8) -->
                    <circle cx="380" cy="145" r="20" class="hotspot" data-slug="fuel-injectors" title="Fuel Injectors"/>
                    <circle cx="380" cy="145" r="15" class="hotspot-pulse"/>
                    
                    <!-- Alternator (7) -->
                    <circle cx="580" cy="230" r="22" class="hotspot" data-slug="alternators" title="Alternator Assembly"/>
                    <circle cx="580" cy="230" r="17" class="hotspot-pulse"/>
                </svg>
            </div>

            <!-- 2. Brakes Diagram (Initially Hidden) -->
            <div id="brakes-diagram" class="diagram-svg-container p-4 mb-4" style="display:none;">
                <svg viewBox="0 0 800 400" width="100%" height="100%" xmlns="http://www.w3.org/2000/svg">
                    <!-- Caliper and rotor layout blueprint -->
                    <circle cx="400" cy="200" r="130" fill="none" stroke="#475569" stroke-width="3" stroke-dasharray="5,5"/>
                    <circle cx="400" cy="200" r="110" fill="none" stroke="#334155" stroke-width="2"/>
                    
                    <!-- Hub -->
                    <circle cx="400" cy="200" r="45" fill="none" stroke="#475569" stroke-width="3"/>
                    <circle cx="400" cy="200" r="10" fill="none" stroke="#64748b" stroke-width="2"/>
                    <circle cx="370" cy="200" r="5" fill="#475569"/>
                    <circle cx="430" cy="200" r="5" fill="#475569"/>
                    <circle cx="400" cy="170" r="5" fill="#475569"/>
                    <circle cx="400" cy="230" r="5" fill="#475569"/>

                    <!-- Caliper housing -->
                    <path d="M 430,90 C 490,100 520,130 520,190 L 480,180 C 480,140 460,120 420,110 Z" fill="none" stroke="#64748b" stroke-width="3"/>
                    
                    <!-- Brake Rotor label -->
                    <path d="M 280,240 L 310,220" stroke="#f75d00" stroke-width="2" stroke-dasharray="2,2"/>
                    <text x="240" y="250" fill="#cbd5e1" font-size="12" text-anchor="middle">Brake Rotors</text>

                    <!-- Brake Pads label -->
                    <path d="M 500,105 L 530,75" stroke="#f75d00" stroke-width="2" stroke-dasharray="2,2"/>
                    <text x="560" y="65" fill="#cbd5e1" font-size="12" text-anchor="middle">Brake Pads</text>

                    <!-- Clickable Brakes Hotspots -->
                    <!-- Brake Rotors (10) -->
                    <circle cx="310" cy="220" r="22" class="hotspot" data-slug="brake-rotors" title="Brake Rotors"/>
                    <circle cx="310" cy="220" r="17" class="hotspot-pulse"/>
                    
                    <!-- Brake Pads (9) -->
                    <circle cx="500" cy="105" r="22" class="hotspot" data-slug="brake-pads" title="Brake Pads"/>
                    <circle cx="500" cy="105" r="17" class="hotspot-pulse"/>
                </svg>
            </div>
        </div>
    </div>

    <!-- Product matching display section -->
    <div class="row justify-content-center">
        <div class="col-lg-10">
            <div class="bg-white p-4 border rounded shadow-sm">
                <h4 class="fw-bold mb-3 text-dark border-bottom pb-2" id="resultsTitle">Select a Hotspot above</h4>
                
                <div id="diagramLoader" class="text-center py-5" style="display:none;">
                    <div class="spinner-border text-orange" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>

                <div id="diagramProductResults" class="row g-3">
                    <div class="col-12 text-center py-5 text-muted">
                        <i class="fa fa-mouse-pointer fa-3x mb-3 text-orange d-block"></i>
                        Click any of the glowing orange hotspots on the engineering diagrams above to display product listings.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Tab toggle controller
    $('.diagram-tab-btn').on('click', function() {
        var btn = $(this);
        var targetId = btn.data('target');
        
        $('.diagram-tab-btn').removeClass('active');
        btn.addClass('active');
        
        $('.diagram-svg-container').hide();
        $('#' + targetId).fadeIn();
        
        // Reset results area
        $('#resultsTitle').text('Select a Hotspot above');
        $('#diagramProductResults').html(`
            <div class="col-12 text-center py-5 text-muted">
                <i class="fa fa-mouse-pointer fa-3x mb-3 text-orange d-block"></i>
                Click any of the glowing orange hotspots on the engineering diagrams above to display product listings.
            </div>
        `);
    });

    // Hotspot Click Event
    $('.hotspot').on('click', function() {
        var spot = $(this);
        var slug = spot.data('slug');
        var title = spot.attr('title');

        $('.hotspot').removeClass('active');
        spot.addClass('active');

        $('#resultsTitle').text('Matching Catalog Parts: ' + title);
        
        // Fetch matching items via AJAX
        $('#diagramProductResults').hide();
        $('#diagramLoader').show();

        $.ajax({
            url: 'api/filter.php',
            type: 'GET',
            data: {
                categories: [slug]
            },
            dataType: 'json',
            success: function(response) {
                $('#diagramLoader').hide();
                var container = $('#diagramProductResults');
                container.empty();

                if (response.success && response.products && response.products.length > 0) {
                    $.each(response.products, function(index, p) {
                        var imgs = [];
                        try {
                            imgs = JSON.parse(p.images);
                        } catch(e) {}
                        var img = (imgs && imgs.length > 0) ? imgs[0] : 'no-image.jpg';
                        
                        var badgeHtml = '';
                        if (p.condition === 'new') badgeHtml = '<span class="badge bg-success badge-condition">New</span>';
                        else if (p.condition === 'used') badgeHtml = '<span class="badge bg-warning text-dark badge-condition">Used</span>';
                        else badgeHtml = '<span class="badge bg-info badge-condition">Refurbished</span>';

                        var cardHtml = `
                            <div class="col-md-4 col-sm-6 mb-3">
                                <div class="product-card card h-100 shadow-sm border">
                                    <div class="card-img-container" style="height:140px;">
                                        <img src="assets/uploads/${img}" alt="${p.name}" onerror="this.src='https://placehold.co/180x180?text=Auto+Part'">
                                        ${badgeHtml}
                                    </div>
                                    <div class="card-body d-flex flex-column p-2">
                                        <small class="text-muted" style="font-size:10px;">${p.brand}</small>
                                        <h6 class="fw-bold text-dark text-truncate mb-1" style="font-size: 13px;">
                                            <a href="product-details.php?slug=${p.slug}" class="text-decoration-none text-dark hover-orange">${p.name}</a>
                                        </h6>
                                        <div class="text-orange fw-bold mb-2 small">₹${parseFloat(p.price).toFixed(2)}</div>
                                        <a href="product-details.php?slug=${p.slug}" class="btn btn-accent btn-xs w-100 mt-auto py-1" style="font-size:11px;">
                                            <i class="fa fa-shopping-cart me-1"></i>View Details
                                        </a>
                                    </div>
                                </div>
                            </div>
                        `;
                        container.append(cardHtml);
                    });
                    container.fadeIn();
                } else {
                    container.html('<div class="col-12 text-center py-5 text-muted">No spare parts found in this subcategory.</div>').fadeIn();
                }
            },
            error: function() {
                $('#diagramLoader').hide();
                $('#diagramProductResults').html('<div class="col-12 alert alert-danger">Error loading category details.</div>').fadeIn();
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
