(function($) {
    'use strict';

    class SecurityGroupManager {
        constructor() {
            this.map = null;
            this.drawControl = null;
            this.coverageLayer = null;
            
            this.initializeEventListeners();
            this.initializeMap();
        }

        initializeEventListeners() {
            // Join group
            $(document).on('click', '.join-group', (e) => {
                const groupId = $(e.currentTarget).data('id');
                this.joinGroup(groupId);
            });

            // Leave group
            $(document).on('click', '.leave-group', (e) => {
                const groupId = $(e.currentTarget).data('id');
                this.leaveGroup(groupId);
            });

            // Edit group details
            $(document).on('click', '.edit-group', (e) => {
                const groupId = $(e.currentTarget).data('id');
                this.editGroupDetails(groupId);
            });

            // Manage members
            $(document).on('click', '.manage-members', (e) => {
                const groupId = $(e.currentTarget).data('id');
                this.manageMembersList(groupId);
            });

            // Edit coverage area
            $(document).on('click', '.edit-coverage', (e) => {
                const groupId = $(e.currentTarget).data('id');
                this.editCoverageArea(groupId);
            });

            // Group form submission
            $('#group-edit-form').on('submit', (e) => {
                e.preventDefault();
                this.saveGroupDetails($(e.currentTarget));
            });

            // Member management form submission
            $('#member-management-form').on('submit', (e) => {
                e.preventDefault();
                this.saveMemberChanges($(e.currentTarget));
            });
        }

        initializeMap() {
            const $map = $('#coverage-map');
            if (!$map.length) return;

            this.map = new SandCrimeMap('coverage-map', {
                enableDraw: SandCrime_Group_Manager.canManageGroup,
                enablePolygons: true
            });

            // Load coverage area if exists
            const coverage = $map.data('coverage');
            if (coverage) {
                this.displayCoverageArea(coverage);
            }
        }

        joinGroup(groupId) {
            if (!confirm('Are you sure you want to join this security group?')) {
                return;
            }

            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'join_security_group',
                    nonce: sandcrimeGroups.nonce,
                    group_id: groupId
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                }
            });
        }

        leaveGroup(groupId) {
            if (!confirm('Are you sure you want to leave this security group?')) {
                return;
            }

            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'leave_security_group',
                    nonce: sandcrimeGroups.nonce,
                    group_id: groupId
                },
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                }
            });
        }

        editGroupDetails(groupId) {
            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_group_edit_form',
                    nonce: sandcrimeGroups.nonce,
                    group_id: groupId
                },
                success: (response) => {
                    if (response.success) {
                        $('#group-modal .modal-content').html(response.data);
                        $('#group-modal').show();
                    }
                }
            });
        }

        manageMembersList(groupId) {
            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: {
                    action: 'get_group_members',
                    nonce: sandcrimeGroups.nonce,
                    group_id: groupId
                },
                success: (response) => {
                    if (response.success) {
                        $('#members-modal .modal-content').html(response.data);
                        $('#members-modal').show();
                    }
                }
            });
        }

        editCoverageArea(groupId) {
            if (!this.map) return;

            this.map.enableDrawing();
            $('#coverage-modal').show();
        }

        displayCoverageArea(coverage) {
            if (!this.map) return;

            if (this.coverageLayer) {
                this.map.map.removeLayer(this.coverageLayer);
            }

            this.coverageLayer = L.geoJSON(JSON.parse(coverage), {
                style: {
                    fillColor: '#3388ff',
                    fillOpacity: 0.2,
                    color: '#3388ff',
                    weight: 2
                }
            }).addTo(this.map.map);

            this.map.map.fitBounds(this.coverageLayer.getBounds());
        }

        saveGroupDetails($form) {
            const formData = new FormData($form[0]);
            formData.append('action', 'save_group_details');
            formData.append('nonce', sandcrimeGroups.nonce);

            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                }
            });
        }

        saveMemberChanges($form) {
            $.ajax({
                url: sandcrimeGroups.ajaxUrl,
                method: 'POST',
                data: $form.serialize() + '&action=save_member_changes&nonce=' + sandcrimeGroups.nonce,
                success: (response) => {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data);
                    }
                }
            });
        }
    }

    // Initialize security group manager when document is ready
    $(document).ready(() => {
        new SecurityGroupManager();
    });

})(jQuery);