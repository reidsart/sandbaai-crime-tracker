(function($) {
    'use strict';

    // Photo upload handling
    class PhotoUploadHandler {
        constructor(formEl) {
            this.form = formEl;
            this.photoInput = this.form.find('#photo-input');
            this.previewContainer = this.form.find('#photo-previews');
            this.addButton = this.form.find('#add-photo');
            this.photos = [];

            this.initializeEventListeners();
        }

        initializeEventListeners() {
            this.addButton.on('click', () => this.photoInput.click());
            
            this.photoInput.on('change', (e) => {
                const files = e.target.files;
                this.handleFileSelection(files);
            });

            this.previewContainer.on('click', '.remove-photo', (e) => {
                const index = $(e.target).closest('.photo-preview').data('index');
                this.removePhoto(index);
            });
        }

        handleFileSelection(files) {
            const remainingSlots = 5 - this.photos.length;
            const filesToAdd = Array.from(files).slice(0, remainingSlots);

            filesToAdd.forEach(file => {
                if (this.validateFile(file)) {
                    this.addPhoto(file);
                }
            });

            // Reset input
            this.photoInput.val('');
        }

        validateFile(file) {
            if (!sandcrimeForm.allowedTypes.includes(file.type)) {
                alert('Invalid file type. Please upload JPG or PNG images only.');
                return false;
            }

            if (file.size > sandcrimeForm.maxFileSize) {
                alert(`File too large. Maximum size is ${this.formatFileSize(sandcrimeForm.maxFileSize)}.`);
                return false;
            }

            return true;
        }

        addPhoto(file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                const index = this.photos.length;
                this.photos.push(file);

                const preview = $(`
                    <div class="photo-preview" data-index="${index}" 
                         style="background-image: url(${e.target.result})">
                        <div class="remove-photo">×</div>
                    </div>
                `);

                this.previewContainer.append(preview);
                
                if (this.photos.length >= 5) {
                    this.addButton.prop('disabled', true);
                }
            };
            reader.readAsDataURL(file);
        }

        removePhoto(index) {
            this.photos.splice(index, 1);
            this.updatePreviews();
            this.addButton.prop('disabled', false);
        }

        updatePreviews() {
            this.previewContainer.empty();
            this.photos.forEach((file, index) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    const preview = $(`
                        <div class="photo-preview" data-index="${index}" 
                             style="background-image: url(${e.target.result})">
                            <div class="remove-photo">×</div>
                        </div>
                    `);
                    this.previewContainer.append(preview);
                };
                reader.readAsDataURL(file);
            });
        }

        getPhotos() {
            return this.photos;
        }

        formatFileSize(bytes) {
            const units = ['B', 'KB', 'MB'];
            let size = bytes;
            let unitIndex = 0;

            while (size >= 1024 && unitIndex < units.length - 1) {
                size /= 1024;
                unitIndex++;
            }

            return `${Math.round(size)} ${units[unitIndex]}`;
        }
    }

    // Form submission handling
    $(document).ready(function() {
        const $form = $('#sandcrime-report-form');
        const photoHandler = new PhotoUploadHandler($form);
        const map = new SandCrimeMap('report-map', {
            enableMarkers: true,
            onMarkerPlaced: function(lat, lng) {
                $('#report-lat').val(lat);
                $('#report-lng').val(lng);
            }
        });

        $form.on('submit', function(e) {
            e.preventDefault();

            const $submitButton = $form.find('button[type="submit"]');
            const $status = $form.find('.submit-status');
            
            // Create FormData
            const formData = new FormData(this);
            
            // Add photos
            const photos = photoHandler.getPhotos();
            photos.forEach(file => {
                formData.append('photos[]', file);
            });

            // Disable submit button
            $submitButton.prop('disabled', true);
            $status.removeClass('error success').text('Submitting report...');

            // Submit form
            $.ajax({
                url: sandcrimeForm.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        $status.addClass('success').text(response.data.message);
                        $form[0].reset();
                        photoHandler.updatePreviews();
                        map.clearMarkers();
                    } else {
                        $status.addClass('error').text(response.data);
                    }
                },
                error: function() {
                    $status.addClass('error').text('An error occurred. Please try again.');
                },
                complete: function() {
                    $submitButton.prop('disabled', false);
                }
            });
        });
    });

})(jQuery);