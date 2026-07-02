/**
 * Namma AutoParts - Fitment Finder AJAX cascading dropdown controller
 */
$(document).ready(function() {
    var makeSelect = $('#fitmentMake');
    var modelSelect = $('#fitmentModel');
    var yearSelect = $('#fitmentYear');
    var engineSelect = $('#fitmentEngine');
    var submitBtn = $('#fitmentSubmitBtn');
    
    // Load Makes on Load
    if (makeSelect.length > 0) {
        loadMakes();
    }

    makeSelect.on('change', function() {
        var make = $(this).val();
        modelSelect.prop('disabled', true).html('<option value="">Loading...</option>');
        yearSelect.prop('disabled', true).html('<option value="">Select Year</option>');
        engineSelect.prop('disabled', true).html('<option value="">Select Engine</option>');
        submitBtn.prop('disabled', true);
        
        if (make) {
            $.getJSON('api/fitment.php', { action: 'models', make: make }, function(data) {
                modelSelect.html('<option value="">Select Model</option>').prop('disabled', false);
                $.each(data, function(index, value) {
                    modelSelect.append(`<option value="${value}">${value}</option>`);
                });
            });
        } else {
            modelSelect.html('<option value="">Select Model</option>').prop('disabled', true);
        }
    });

    modelSelect.on('change', function() {
        var make = makeSelect.val();
        var model = $(this).val();
        yearSelect.prop('disabled', true).html('<option value="">Loading...</option>');
        engineSelect.prop('disabled', true).html('<option value="">Select Engine</option>');
        submitBtn.prop('disabled', true);
        
        if (model) {
            $.getJSON('api/fitment.php', { action: 'years', make: make, model: model }, function(data) {
                yearSelect.html('<option value="">Select Year</option>').prop('disabled', false);
                $.each(data, function(index, value) {
                    yearSelect.append(`<option value="${value}">${value}</option>`);
                });
            });
        } else {
            yearSelect.html('<option value="">Select Year</option>').prop('disabled', true);
        }
    });

    yearSelect.on('change', function() {
        var make = makeSelect.val();
        var model = modelSelect.val();
        var year = $(this).val();
        engineSelect.prop('disabled', true).html('<option value="">Loading...</option>');
        submitBtn.prop('disabled', true);
        
        if (year) {
            $.getJSON('api/fitment.php', { action: 'engines', make: make, model: model, year: year }, function(data) {
                engineSelect.html('<option value="">Select Engine Variant</option>').prop('disabled', false);
                $.each(data, function(index, item) {
                    engineSelect.append(`<option value="${item.id}">${item.engine_type} (${item.fuel_type})</option>`);
                });
            });
        } else {
            engineSelect.html('<option value="">Select Engine Variant</option>').prop('disabled', true);
        }
    });

    engineSelect.on('change', function() {
        var val = $(this).val();
        if (val) {
            $('#selectedVehicleId').val(val);
            $('#selectedYear').val(yearSelect.val());
            submitBtn.prop('disabled', false);
        } else {
            $('#selectedVehicleId').val('');
            $('#selectedYear').val('');
            submitBtn.prop('disabled', true);
        }
    });

    function loadMakes() {
        $.getJSON('api/fitment.php', { action: 'makes' }, function(data) {
            makeSelect.html('<option value="">Select Make</option>');
            $.each(data, function(index, value) {
                makeSelect.append(`<option value="${value}">${value}</option>`);
            });
        });
    }
});
