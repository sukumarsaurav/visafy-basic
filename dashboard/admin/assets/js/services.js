$(document).ready(function() {
    // Initialize DataTable
    const servicesTable = $('#services-table').DataTable({
        ajax: {
            url: 'ajax/get_services.php',
            dataSrc: ''
        },
        columns: [
            { data: 'country_name' },
            { data: 'visa_type_name' },
            { data: 'service_type_name' },
            { data: 'consultation_mode_name' },
            { 
                data: 'price',
                render: function(data) {
                    return `$${parseFloat(data).toFixed(2)}`;
                }
            },
            {
                data: 'is_active',
                render: function(data) {
                    return data == 1 ? 
                        '<span class="badge bg-success">Active</span>' : 
                        '<span class="badge bg-danger">Inactive</span>';
                }
            },
            {
                data: null,
                render: function(data) {
                    return `
                        <button class="btn btn-sm btn-primary edit-btn" data-id="${data.id}">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-sm btn-danger delete-btn" data-id="${data.id}">
                            <i class="fas fa-trash"></i>
                        </button>
                    `;
                }
            }
        ],
        order: [[0, 'asc']],
        responsive: true
    });

    // Handle country change
    $('#country').change(function() {
        const countryId = $(this).val();
        const visaTypeSelect = $('#visa-type');
        
        visaTypeSelect.prop('disabled', true).empty()
            .append('<option value="">Select Visa Type</option>');
        
        if (countryId) {
            $.get('ajax/get_visa_types.php', { country_id: countryId })
                .done(function(data) {
                    data.forEach(function(type) {
                        visaTypeSelect.append(
                            `<option value="${type.id}">${type.name}</option>`
                        );
                    });
                    visaTypeSelect.prop('disabled', false);
                });
        }
    });

    // Show add service modal
    $('#add-service-btn').click(function() {
        $('#config-id').val('');
        $('#service-form')[0].reset();
        $('#visa-type').prop('disabled', true);
        $('#service-modal').modal('show');
    });

    // Handle edit button click
    $(document).on('click', '.edit-btn', function() {
        const id = $(this).data('id');
        
        $.get('ajax/get_service.php', { id: id })
            .done(function(data) {
                $('#config-id').val(data.id);
                $('#country').val(data.country_id).trigger('change');
                
                // Wait for visa types to load
                setTimeout(() => {
                    $('#visa-type').val(data.visa_type_id);
                }, 500);
                
                $('#service-type').val(data.service_type_id);
                $('#consultation-mode').val(data.consultation_mode_id);
                $('#price').val(data.price);
                $('#is-active').prop('checked', data.is_active == 1);
                
                $('#service-modal').modal('show');
            });
    });

    // Handle save button click
    $('#save-service-btn').click(function() {
        const form = $('#service-form');
        
        if (!form[0].checkValidity()) {
            form[0].reportValidity();
            return;
        }
        
        const data = {
            id: $('#config-id').val(),
            visa_type_id: $('#visa-type').val(),
            service_type_id: $('#service-type').val(),
            consultation_mode_id: $('#consultation-mode').val(),
            price: $('#price').val(),
            is_active: $('#is-active').is(':checked') ? 1 : 0
        };
        
        $.post('ajax/save_service.php', data)
            .done(function(response) {
                if (response.success) {
                    $('#service-modal').modal('hide');
                    servicesTable.ajax.reload();
                    toastr.success('Service configuration saved successfully');
                } else {
                    toastr.error(response.message || 'Failed to save service configuration');
                }
            })
            .fail(function() {
                toastr.error('An error occurred while saving');
            });
    });

    // Handle delete button click
    $(document).on('click', '.delete-btn', function() {
        const id = $(this).data('id');
        
        if (confirm('Are you sure you want to delete this service configuration?')) {
            $.post('ajax/delete_service.php', { id: id })
                .done(function(response) {
                    if (response.success) {
                        servicesTable.ajax.reload();
                        toastr.success('Service configuration deleted successfully');
                    } else {
                        toastr.error(response.message || 'Failed to delete service configuration');
                    }
                })
                .fail(function() {
                    toastr.error('An error occurred while deleting');
                });
        }
    });
});
