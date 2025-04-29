// Document ready function
$(document).ready(function() {
    // Initialize variables
    let cropper = null;
    
    // Handle Edit Profile button click
    $('#edit-profile-btn').click(function() {
        enableProfileEdit(true);
    });
    
    // Handle Cancel Edit button click
    $('#cancel-edit-btn').click(function() {
        enableProfileEdit(false);
        resetForm('#profile-form');
    });
    
    // Handle Profile Form submission
    $('#profile-form').submit(function(e) {
        e.preventDefault();
        saveProfile();
    });
    
    // Handle Password Form submission
    $('#password-form').submit(function(e) {
        e.preventDefault();
        changePassword();
    });
    
    // Handle Notification Form submission
    $('#notification-form').submit(function(e) {
        e.preventDefault();
        saveNotificationPreferences();
    });
    
    // Handle Upload Photo button click
    $('#upload-photo-btn, .profile-image-container').click(function() {
        $('#photo-upload').click();
    });
    
    // Handle file selection
    $('#photo-upload').change(function() {
        if (this.files && this.files[0]) {
            const file = this.files[0];
            
            // Check file type
            if (!file.type.match('image.*')) {
                showAlert('Please select an image file (jpg, png, gif).', 'error');
                return;
            }
            
            // Check file size
            if (file.size > 5 * 1024 * 1024) { // 5MB
                showAlert('Image size should be less than 5MB.', 'error');
                return;
            }
            
            const reader = new FileReader();
            reader.onload = function(e) {
                // Set the crop image source
                $('#crop-image').attr('src', e.target.result);
                
                // Show the crop modal
                $('#crop-modal').modal('show');
                
                // Initialize cropper after modal is shown
                $('#crop-modal').on('shown.bs.modal', function() {
                    // Destroy existing cropper if exists
                    if (cropper) {
                        cropper.destroy();
                    }
                    
                    // Initialize cropper
                    cropper = new Cropper(document.getElementById('crop-image'), {
                        aspectRatio: 1,
                        viewMode: 1,
                        dragMode: 'move',
                        autoCropArea: 0.8,
                        restore: false,
                        guides: true,
                        center: true,
                        highlight: false,
                        cropBoxMovable: true,
                        cropBoxResizable: true,
                        toggleDragModeOnDblclick: false
                    });
                });
            };
            
            reader.readAsDataURL(file);
        }
    });
    
    // Handle crop button click
    $('#crop-btn').click(function() {
        if (!cropper) return;
        
        // Get the cropped canvas
        const canvas = cropper.getCroppedCanvas({
            width: 300,
            height: 300,
            minWidth: 100,
            minHeight: 100,
            maxWidth: 4096,
            maxHeight: 4096,
            fillColor: '#fff',
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        
        // Convert canvas to blob
        canvas.toBlob(function(blob) {
            uploadProfileImage(blob);
        });
    });
    
    // Handle password input for strength check
    $('#new-password').on('input', function() {
        const password = $(this).val();
        if (password) {
            checkPasswordStrength(password);
            $('.password-strength').show();
        } else {
            $('.password-strength').hide();
        }
    });
    
    // Handle resend verification email
    $('#resend-verification').click(function(e) {
        e.preventDefault();
        resendVerificationEmail();
    });
    
    // Initialize tooltips
    $('[data-bs-toggle="tooltip"]').tooltip();
});

// Function to enable/disable profile editing
function enableProfileEdit(enable) {
    const inputs = $('#profile-form input');
    const editBtn = $('#edit-profile-btn');
    const saveBtn = $('#save-profile-btn');
    const cancelBtn = $('#cancel-edit-btn');
    
    if (enable) {
        inputs.removeAttr('readonly');
        editBtn.hide();
        saveBtn.show();
        cancelBtn.show();
        // Email should remain readonly
        $('#email').attr('readonly', 'readonly');
    } else {
        inputs.attr('readonly', 'readonly');
        editBtn.show();
        saveBtn.hide();
        cancelBtn.hide();
    }
}

// Function to reset form to original values
function resetForm(formSelector) {
    $(formSelector)[0].reset();
}

// Function to save profile data
function saveProfile() {
    const formData = {
        first_name: $('#first-name').val(),
        last_name: $('#last-name').val()
    };
    
    $.ajax({
        url: 'ajax/update_profile.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Profile updated successfully', 'success');
                enableProfileEdit(false);
            } else {
                showAlert('Error: ' + response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            showAlert('An error occurred while updating profile', 'error');
        }
    });
}

// Function to change password
function changePassword() {
    const currentPassword = $('#current-password').val();
    const newPassword = $('#new-password').val();
    const confirmPassword = $('#confirm-password').val();
    
    // Validate passwords
    if (!currentPassword) {
        showAlert('Please enter your current password', 'error');
        return;
    }
    
    if (!newPassword) {
        showAlert('Please enter a new password', 'error');
        return;
    }
    
    if (newPassword !== confirmPassword) {
        showAlert('New password and confirmation do not match', 'error');
        return;
    }
    
    $.ajax({
        url: 'ajax/change_password.php',
        type: 'POST',
        data: {
            current_password: currentPassword,
            new_password: newPassword,
            confirm_password: confirmPassword
        },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Password changed successfully', 'success');
                $('#password-form')[0].reset();
                $('.password-strength').hide();
            } else {
                showAlert('Error: ' + response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            showAlert('An error occurred while changing password', 'error');
        }
    });
}

// Function to save notification preferences
function saveNotificationPreferences() {
    const formData = $('#notification-form').serialize();
    
    $.ajax({
        url: 'ajax/update_notification_preferences.php',
        type: 'POST',
        data: formData,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Notification preferences updated successfully', 'success');
            } else {
                showAlert('Error: ' + response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            showAlert('An error occurred while updating notification preferences', 'error');
        }
    });
}

// Function to upload profile image
function uploadProfileImage(blob) {
    const formData = new FormData();
    formData.append('profile_image', blob, 'profile.jpg');
    
    $.ajax({
        url: 'ajax/upload_profile_image.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                // Close the modal
                $('#crop-modal').modal('hide');
                
                // Update the profile image
                $('#profile-image').attr('src', response.image_url);
                
                showAlert('Profile image updated successfully', 'success');
            } else {
                showAlert('Error: ' + response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            showAlert('An error occurred while uploading image', 'error');
        }
    });
}

// Function to check password strength
function checkPasswordStrength(password) {
    let strength = 0;
    let strengthText = '';
    let color = '';
    
    // Check length
    if (password.length >= 8) {
        strength += 1;
    }
    
    // Check for lowercase letter
    if (password.match(/[a-z]/)) {
        strength += 1;
    }
    
    // Check for uppercase letter
    if (password.match(/[A-Z]/)) {
        strength += 1;
    }
    
    // Check for number
    if (password.match(/\d/)) {
        strength += 1;
    }
    
    // Check for special character
    if (password.match(/[^a-zA-Z\d]/)) {
        strength += 1;
    }
    
    // Set indicator width and color based on strength
    const widthPercent = (strength / 5) * 100;
    
    // Set text and color based on strength
    switch (strength) {
        case 0:
        case 1:
            strengthText = 'Very Weak';
            color = '#ff4d4d';
            break;
        case 2:
            strengthText = 'Weak';
            color = '#ffa64d';
            break;
        case 3:
            strengthText = 'Moderate';
            color = '#ffff4d';
            break;
        case 4:
            strengthText = 'Strong';
            color = '#4dff4d';
            break;
        case 5:
            strengthText = 'Very Strong';
            color = '#4d4dff';
            break;
    }
    
    // Update UI
    $('#strength-indicator').css({
        'width': widthPercent + '%',
        'background-color': color
    });
    
    $('#strength-text').text(strengthText);
}

// Function to resend verification email
function resendVerificationEmail() {
    $.ajax({
        url: 'ajax/resend_verification.php',
        type: 'POST',
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                showAlert('Verification email sent successfully. Please check your inbox.', 'success');
            } else {
                showAlert('Error: ' + response.error, 'error');
            }
        },
        error: function(xhr, status, error) {
            showAlert('An error occurred while sending verification email', 'error');
        }
    });
}

// Function to show alert messages
function showAlert(message, type) {
    // Create alert element
    const alertDiv = $('<div class="alert alert-dismissible fade show"></div>');
    alertDiv.addClass(type === 'success' ? 'alert-success' : 'alert-danger');
    alertDiv.html(`
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    `);
    
    // Append to DOM
    $('#alerts-container').length ? 
        $('#alerts-container').append(alertDiv) : 
        $('.profile-container').prepend($('<div id="alerts-container"></div>').append(alertDiv));
    
    // Auto-dismiss after 5 seconds
    setTimeout(function() {
        alertDiv.alert('close');
    }, 5000);
}
