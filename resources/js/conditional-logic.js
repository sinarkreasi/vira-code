/**
 * Conditional Logic JavaScript
 * 
 * @package ViraCode
 */

(function($) {
    'use strict';

    // Conditional Logic Manager
    window.ViraConditionalLogic = {
        
        // Configuration
        config: {
            conditionTypes: {
                'page_type': {
                    label: 'Page Type',
                    operators: {
                        'equals': 'Equals',
                        'not_equals': 'Not Equals'
                    },
                    values: {
                        'home': 'Home Page',
                        'single': 'Single Post',
                        'page': 'Page',
                        'archive': 'Archive',
                        'category': 'Category',
                        'tag': 'Tag',
                        'custom_post_type': 'Custom Post Type'
                    }
                },
                'url_pattern': {
                    label: 'URL Pattern',
                    operators: {
                        'equals': 'Equals',
                        'contains': 'Contains',
                        'starts_with': 'Starts With',
                        'ends_with': 'Ends With',
                        'regex': 'Regular Expression'
                    },
                    inputType: 'text'
                },
                'user_role': {
                    label: 'User Role',
                    operators: {
                        'equals': 'Equals',
                        'not_equals': 'Not Equals'
                    },
                    values: {
                        'administrator': 'Administrator',
                        'editor': 'Editor',
                        'author': 'Author',
                        'subscriber': 'Subscriber',
                        'logged_in': 'Logged In',
                        'logged_out': 'Logged Out',
                        'guest': 'Guest'
                    }
                },
                'device_type': {
                    label: 'Device Type',
                    operators: {
                        'equals': 'Equals',
                        'not_equals': 'Not Equals'
                    },
                    values: {
                        'desktop': 'Desktop',
                        'mobile': 'Mobile',
                        'tablet': 'Tablet'
                    }
                },
                'date_range': {
                    label: 'Date Range',
                    operators: {
                        'between': 'Between',
                        'before': 'Before',
                        'after': 'After',
                        'equals': 'On Date'
                    },
                    inputType: 'date'
                },
                'custom_php': {
                    label: 'Custom PHP',
                    operators: {
                        'custom': 'Custom Code'
                    },
                    inputType: 'textarea'
                }
            }
        },

        // Current rules data
        rules: {
            enabled: false,
            logic: 'AND',
            groups: []
        },

        // Initialize
        init: function() {
            this.bindEvents();
            this.loadExistingRules();
        },

        // Bind events
        bindEvents: function() {
            var self = this;

            // Toggle conditional logic
            $('#conditional-logic-enabled').on('change', function() {
                self.toggleConditionalLogic($(this).is(':checked'));
            });

            // Add condition group
            $('#add-condition-group').on('click', function() {
                self.addConditionGroup();
            });

            // Check conditional logic rules
            $('#check-conditional-logic').on('click', function() {
                self.checkConditionalLogic();
            });

            // Import/Export rules
            $('#import-rules').on('click', function() {
                self.importRules();
            });

            $('#export-rules').on('click', function() {
                self.exportRules();
            });

            // Dynamic event binding for condition management
            $(document).on('click', '.add-condition', function() {
                var groupId = $(this).closest('.rule-group').data('group-id');
                self.addCondition(groupId);
            });

            $(document).on('click', '.remove-condition', function() {
                var groupId = $(this).closest('.rule-group').data('group-id');
                var conditionId = $(this).closest('.condition-row').data('condition-id');
                self.removeCondition(groupId, conditionId);
            });

            $(document).on('click', '.remove-group', function() {
                var groupId = $(this).closest('.rule-group').data('group-id');
                self.removeConditionGroup(groupId);
            });

            $(document).on('change', '.condition-type', function() {
                var groupId = $(this).closest('.rule-group').data('group-id');
                var conditionId = $(this).closest('.condition-row').data('condition-id');
                self.updateConditionOperators(groupId, conditionId, $(this).val());
            });

            $(document).on('change', '.condition-operator', function() {
                var groupId = $(this).closest('.rule-group').data('group-id');
                var conditionId = $(this).closest('.condition-row').data('condition-id');
                self.updateConditionValue(groupId, conditionId, $(this).val());
            });

            $(document).on('change', '.group-logic', function() {
                var groupId = $(this).closest('.rule-group').data('group-id');
                self.updateGroupLogic(groupId, $(this).val());
            });

            // Real-time validation and updates
            $(document).on('input change', '.condition-value', function() {
                self.validateCondition($(this));
                self.updateRulesData();
                self.updatePreview();
            });

            // Update rules when any condition changes
            $(document).on('change', '.condition-type, .condition-operator, .group-logic', function() {
                self.updateRulesData();
                self.updatePreview();
            });

            // Initialize drag and drop after DOM changes
            $(document).on('DOMNodeInserted', '.rule-group', function() {
                self.initializeDragAndDrop();
            });
        },

        // Toggle conditional logic
        toggleConditionalLogic: function(enabled) {
            this.rules.enabled = enabled;
            
            if (enabled) {
                $('.vira-conditional-content').slideDown();
                if (this.rules.groups.length === 0) {
                    this.addConditionGroup();
                }
            } else {
                $('.vira-conditional-content').slideUp();
            }
            
            this.updateRulesData();
            this.updatePreview();
        },

        // Add condition group
        addConditionGroup: function() {
            var groupId = 'group_' + Date.now();
            var group = {
                id: groupId,
                logic: 'AND',
                conditions: []
            };

            this.rules.groups.push(group);
            this.renderConditionGroup(group);
            this.addCondition(groupId);
            this.updateRulesData();
        },

        // Remove condition group
        removeConditionGroup: function(groupId) {
            this.rules.groups = this.rules.groups.filter(function(group) {
                return group.id !== groupId;
            });

            $('.rule-group[data-group-id="' + groupId + '"]').remove();
            this.updateRulesData();
            this.updatePreview();
        },

        // Add condition to group
        addCondition: function(groupId) {
            var group = this.rules.groups.find(function(g) {
                return g.id === groupId;
            });

            if (!group) return;

            var conditionId = 'condition_' + Date.now();
            var condition = {
                id: conditionId,
                type: 'page_type',
                operator: 'equals',
                value: 'home'
            };

            group.conditions.push(condition);
            this.renderCondition(groupId, condition);
            this.updateRulesData();
        },

        // Remove condition
        removeCondition: function(groupId, conditionId) {
            var group = this.rules.groups.find(function(g) {
                return g.id === groupId;
            });

            if (!group) return;

            group.conditions = group.conditions.filter(function(condition) {
                return condition.id !== conditionId;
            });

            $('.condition-row[data-condition-id="' + conditionId + '"]').remove();

            // Remove group if no conditions left
            if (group.conditions.length === 0) {
                this.removeConditionGroup(groupId);
            } else {
                this.updateRulesData();
                this.updatePreview();
            }
        },

        // Render condition group
        renderConditionGroup: function(group) {
            var html = '<div class="rule-group" data-group-id="' + group.id + '">';
            html += '<div class="group-header">';
            html += '<span class="group-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>';
            html += '<select class="group-logic">';
            html += '<option value="AND"' + (group.logic === 'AND' ? ' selected' : '') + '>AND (All conditions must match)</option>';
            html += '<option value="OR"' + (group.logic === 'OR' ? ' selected' : '') + '>OR (Any condition can match)</option>';
            html += '</select>';
            html += '<button type="button" class="button button-small remove-group">Remove Group</button>';
            html += '</div>';
            html += '<div class="conditions-container"></div>';
            html += '<div class="group-actions">';
            html += '<button type="button" class="button button-secondary add-condition">Add Condition</button>';
            html += '</div>';
            html += '</div>';

            $('#vira-rule-groups').append(html);
            this.initializeDragAndDrop();
        },

        // Render condition
        renderCondition: function(groupId, condition) {
            var conditionType = this.config.conditionTypes[condition.type];
            var html = '<div class="condition-row" data-condition-id="' + condition.id + '">';
            
            // Drag handle
            html += '<span class="condition-drag-handle dashicons dashicons-menu" title="Drag to reorder"></span>';
            
            // Condition type dropdown
            html += '<select class="condition-type">';
            for (var type in this.config.conditionTypes) {
                html += '<option value="' + type + '"' + (condition.type === type ? ' selected' : '') + '>';
                html += this.config.conditionTypes[type].label;
                html += '</option>';
            }
            html += '</select>';

            // Operator dropdown
            html += '<select class="condition-operator">';
            for (var op in conditionType.operators) {
                html += '<option value="' + op + '"' + (condition.operator === op ? ' selected' : '') + '>';
                html += conditionType.operators[op];
                html += '</option>';
            }
            html += '</select>';

            // Value input
            html += this.renderConditionValue(condition);

            // Remove button
            html += '<button type="button" class="button button-small remove-condition">Remove</button>';
            html += '</div>';

            $('.rule-group[data-group-id="' + groupId + '"] .conditions-container').append(html);
            this.initializeDragAndDrop();
        },

        // Render condition value input
        renderConditionValue: function(condition) {
            var conditionType = this.config.conditionTypes[condition.type];
            var html = '';

            if (conditionType.values) {
                // Dropdown for predefined values
                html += '<select class="condition-value">';
                for (var val in conditionType.values) {
                    html += '<option value="' + val + '"' + (condition.value === val ? ' selected' : '') + '>';
                    html += conditionType.values[val];
                    html += '</option>';
                }
                html += '</select>';
            } else if (conditionType.inputType === 'textarea') {
                // Textarea for custom PHP
                html += '<textarea class="condition-value" placeholder="Enter PHP code...">' + (condition.value || '') + '</textarea>';
            } else if (conditionType.inputType === 'date') {
                // Date input
                html += '<input type="date" class="condition-value" value="' + (condition.value || '') + '">';
            } else {
                // Text input
                html += '<input type="text" class="condition-value" value="' + (condition.value || '') + '" placeholder="Enter value...">';
            }

            return html;
        },

        // Update condition operators when type changes
        updateConditionOperators: function(groupId, conditionId, newType) {
            var group = this.rules.groups.find(function(g) { return g.id === groupId; });
            var condition = group.conditions.find(function(c) { return c.id === conditionId; });
            
            condition.type = newType;
            condition.operator = Object.keys(this.config.conditionTypes[newType].operators)[0];
            condition.value = '';

            var conditionRow = $('.condition-row[data-condition-id="' + conditionId + '"]');
            var operatorSelect = conditionRow.find('.condition-operator');
            var valueContainer = conditionRow.find('.condition-value').parent();

            // Update operators
            operatorSelect.empty();
            var operators = this.config.conditionTypes[newType].operators;
            for (var op in operators) {
                operatorSelect.append('<option value="' + op + '">' + operators[op] + '</option>');
            }

            // Update value input
            conditionRow.find('.condition-value').replaceWith(this.renderConditionValue(condition));
        },

        // Update condition value input when operator changes
        updateConditionValue: function(groupId, conditionId, newOperator) {
            var group = this.rules.groups.find(function(g) { return g.id === groupId; });
            var condition = group.conditions.find(function(c) { return c.id === conditionId; });
            
            condition.operator = newOperator;
            
            // For date range operators, we might need different inputs
            if (condition.type === 'date_range' && newOperator === 'between') {
                var conditionRow = $('.condition-row[data-condition-id="' + conditionId + '"]');
                var valueHtml = '<input type="date" class="condition-value condition-start-date" placeholder="Start date"> ';
                valueHtml += '<input type="date" class="condition-value condition-end-date" placeholder="End date">';
                conditionRow.find('.condition-value').replaceWith(valueHtml);
            }
        },

        // Update group logic
        updateGroupLogic: function(groupId, newLogic) {
            var group = this.rules.groups.find(function(g) { return g.id === groupId; });
            if (group) {
                group.logic = newLogic;
                this.updateRulesData();
                this.updatePreview();
            }
        },

        // Update rules data in hidden field
        updateRulesData: function() {
            // Collect current form data
            var self = this;
            
            this.rules.groups.forEach(function(group) {
                var groupElement = $('.rule-group[data-group-id="' + group.id + '"]');
                group.logic = groupElement.find('.group-logic').val();
                
                group.conditions.forEach(function(condition) {
                    var conditionElement = $('.condition-row[data-condition-id="' + condition.id + '"]');
                    condition.type = conditionElement.find('.condition-type').val();
                    condition.operator = conditionElement.find('.condition-operator').val();
                    
                    // Handle different value types
                    if (condition.type === 'date_range' && condition.operator === 'between') {
                        condition.value = {
                            start: conditionElement.find('.condition-start-date').val(),
                            end: conditionElement.find('.condition-end-date').val()
                        };
                    } else {
                        condition.value = conditionElement.find('.condition-value').val();
                    }
                });
            });

            $('#conditional-logic-rules').val(JSON.stringify(this.rules));
        },

        // Update preview
        updatePreview: function() {
            var preview = $('.vira-rule-preview-content');
            
            if (!this.rules.enabled || this.rules.groups.length === 0) {
                preview.html('<em>No conditions set</em>');
                return;
            }

            var html = '';
            var self = this;
            
            this.rules.groups.forEach(function(group, groupIndex) {
                if (groupIndex > 0) {
                    html += '<div class="logic-separator">AND</div>';
                }
                
                html += '<div class="group-preview">';
                html += '<strong>Group ' + (groupIndex + 1) + ' (' + group.logic + '):</strong><br>';
                
                group.conditions.forEach(function(condition, conditionIndex) {
                    if (conditionIndex > 0) {
                        html += ' <em>' + group.logic + '</em> ';
                    }
                    
                    var conditionType = self.config.conditionTypes[condition.type];
                    var operatorLabel = conditionType.operators[condition.operator];
                    var valueLabel = condition.value;
                    
                    if (conditionType.values && conditionType.values[condition.value]) {
                        valueLabel = conditionType.values[condition.value];
                    }
                    
                    html += conditionType.label + ' ' + operatorLabel + ' "' + valueLabel + '"';
                });
                
                html += '</div>';
            });
            
            preview.html(html);
        },

        // Load existing rules
        loadExistingRules: function() {
            // This will be populated from PHP when editing existing snippets
            var existingRules = window.viraConditionalLogicData || null;
            
            if (existingRules && existingRules.groups && existingRules.groups.length > 0) {
                this.rules = existingRules;
                this.renderExistingRules();
                $('#conditional-logic-enabled').prop('checked', true).trigger('change');
            } else {
                // Check if conditional logic is enabled in PHP but no rules data
                if ($('#conditional-logic-enabled').is(':checked')) {
                    this.toggleConditionalLogic(true);
                }
            }
        },

        // Render existing rules
        renderExistingRules: function() {
            var self = this;
            
            this.rules.groups.forEach(function(group) {
                self.renderConditionGroup(group);
                
                group.conditions.forEach(function(condition) {
                    self.renderCondition(group.id, condition);
                });
            });
            
            this.updatePreview();
        },

        // Import rules
        importRules: function() {
            var self = this;
            var input = $('<input type="file" accept=".json">');
            
            input.on('change', function(e) {
                var file = e.target.files[0];
                if (!file) return;
                
                var reader = new FileReader();
                reader.onload = function(e) {
                    try {
                        var rules = JSON.parse(e.target.result);
                        
                        // Check if this is a bulk export format
                        if (Array.isArray(rules) && rules.length > 0 && rules[0].snippet_title) {
                            self.showBulkImportDialog(rules);
                        }
                        // Single rules object
                        else if (rules.groups && Array.isArray(rules.groups)) {
                            // Clear existing rules
                            $('#vira-rule-groups').empty();
                            
                            // Load imported rules
                            window.viraConditionalLogicData = rules;
                            self.rules = rules;
                            self.renderExistingRules();
                            
                            $('#conditional-logic-enabled').prop('checked', rules.enabled || false).trigger('change');
                            
                            alert('Rules imported successfully!');
                        } else {
                            alert('Invalid rules file format.');
                        }
                    } catch (error) {
                        alert('Error parsing rules file: ' + error.message);
                    }
                };
                reader.readAsText(file);
            });
            
            input.click();
        },

        // Show bulk import dialog
        showBulkImportDialog: function(importData) {
            var self = this;
            var dialog = $('<div class="vira-bulk-import-dialog">');
            dialog.html(`
                <h3>Bulk Import Conditional Logic Rules</h3>
                <p>Found ${importData.length} rule sets. Choose import method:</p>
                <div class="import-options">
                    <label>
                        <input type="radio" name="import_method" value="current" checked>
                        Import to current snippet (replace existing rules)
                    </label>
                    <label>
                        <input type="radio" name="import_method" value="server">
                        Server-side bulk import (create/update snippets)
                    </label>
                </div>
                <div class="import-preview">
                    <h4>Preview:</h4>
                    <ul>
                        ${importData.map(item => `<li>${item.snippet_title} (${item.rules.groups ? item.rules.groups.length : 0} rule groups)</li>`).join('')}
                    </ul>
                </div>
                <div class="dialog-actions">
                    <button type="button" class="button button-primary import-confirm">Import</button>
                    <button type="button" class="button import-cancel">Cancel</button>
                </div>
            `);

            $('body').append(dialog);
            dialog.show();

            dialog.find('.import-confirm').on('click', function() {
                var method = dialog.find('input[name="import_method"]:checked').val();
                
                if (method === 'current') {
                    // Import first rule set to current snippet
                    if (importData.length > 0 && importData[0].rules) {
                        $('#vira-rule-groups').empty();
                        window.viraConditionalLogicData = importData[0].rules;
                        self.rules = importData[0].rules;
                        self.renderExistingRules();
                        $('#conditional-logic-enabled').prop('checked', importData[0].rules.enabled || false).trigger('change');
                        alert('Rules imported successfully!');
                    }
                } else {
                    // Server-side bulk import
                    self.serverBulkImport(importData);
                }
                
                dialog.remove();
            });

            dialog.find('.import-cancel').on('click', function() {
                dialog.remove();
            });
        },

        // Server-side bulk import
        serverBulkImport: function(importData) {
            $.post(viraCode.ajaxUrl, {
                action: 'vira_code_import_conditional_rules',
                nonce: viraCode.nonce,
                import_data: JSON.stringify(importData)
            }).done(function(response) {
                if (response.success) {
                    alert(response.data.message);
                } else {
                    alert('Import failed: ' + (response.data.message || 'Unknown error'));
                }
            }).fail(function() {
                alert('Import request failed. Please try again.');
            });
        },

        // Export rules
        exportRules: function() {
            this.updateRulesData();
            
            var self = this;
            var snippetId = $('#snippet-id').val();
            
            // Show export options dialog
            var dialog = $('<div class="vira-export-dialog">');
            dialog.html(`
                <h3>Export Conditional Logic Rules</h3>
                <div class="export-options">
                    <label>
                        <input type="radio" name="export_method" value="current" checked>
                        Export current snippet rules only
                    </label>
                    <label>
                        <input type="radio" name="export_method" value="bulk">
                        Bulk export (select multiple snippets)
                    </label>
                </div>
                <div class="bulk-export-options" style="display: none;">
                    <p>Select snippets to export:</p>
                    <div class="snippet-selection">
                        <p><em>Loading snippets...</em></p>
                    </div>
                </div>
                <div class="dialog-actions">
                    <button type="button" class="button button-primary export-confirm">Export</button>
                    <button type="button" class="button export-cancel">Cancel</button>
                </div>
            `);

            $('body').append(dialog);
            dialog.show();

            // Handle export method change
            dialog.find('input[name="export_method"]').on('change', function() {
                if ($(this).val() === 'bulk') {
                    dialog.find('.bulk-export-options').show();
                    self.loadSnippetsForBulkExport(dialog);
                } else {
                    dialog.find('.bulk-export-options').hide();
                }
            });

            dialog.find('.export-confirm').on('click', function() {
                var method = dialog.find('input[name="export_method"]:checked').val();
                
                if (method === 'current') {
                    // Export current snippet rules
                    var rules = self.rules;
                    var dataStr = JSON.stringify(rules, null, 2);
                    var dataBlob = new Blob([dataStr], {type: 'application/json'});
                    
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(dataBlob);
                    link.download = 'vira-conditional-logic-rules.json';
                    link.click();
                } else {
                    // Bulk export
                    var selectedSnippets = [];
                    dialog.find('.snippet-selection input:checked').each(function() {
                        selectedSnippets.push($(this).val());
                    });
                    
                    if (selectedSnippets.length === 0) {
                        alert('Please select at least one snippet to export.');
                        return;
                    }
                    
                    self.serverBulkExport(selectedSnippets);
                }
                
                dialog.remove();
            });

            dialog.find('.export-cancel').on('click', function() {
                dialog.remove();
            });
        },

        // Load snippets for bulk export
        loadSnippetsForBulkExport: function(dialog) {
            $.post(viraCode.ajaxUrl, {
                action: 'vira_code_get_statistics',
                nonce: viraCode.nonce
            }).done(function(response) {
                // This is a placeholder - we'd need a proper endpoint to get snippet list
                // For now, show a simple message
                dialog.find('.snippet-selection').html(`
                    <p><em>Bulk export functionality requires server-side snippet list. 
                    Please use individual export for now or contact administrator.</em></p>
                `);
            });
        },

        // Server-side bulk export
        serverBulkExport: function(snippetIds) {
            $.post(viraCode.ajaxUrl, {
                action: 'vira_code_export_conditional_rules',
                nonce: viraCode.nonce,
                snippet_ids: snippetIds
            }).done(function(response) {
                if (response.success) {
                    var dataStr = JSON.stringify(response.data.data, null, 2);
                    var dataBlob = new Blob([dataStr], {type: 'application/json'});
                    
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(dataBlob);
                    link.download = response.data.filename || 'vira-bulk-conditional-rules.json';
                    link.click();
                } else {
                    alert('Export failed: ' + (response.data.message || 'Unknown error'));
                }
            }).fail(function() {
                alert('Export request failed. Please try again.');
            });
        },

        // Initialize drag and drop functionality
        initializeDragAndDrop: function() {
            var self = this;
            
            // Make condition rows sortable within groups
            $('.conditions-container').sortable({
                items: '.condition-row',
                handle: '.condition-drag-handle',
                placeholder: 'condition-placeholder',
                tolerance: 'pointer',
                update: function(event, ui) {
                    self.updateConditionOrder();
                }
            });

            // Make rule groups sortable
            $('#vira-rule-groups').sortable({
                items: '.rule-group',
                handle: '.group-drag-handle',
                placeholder: 'group-placeholder',
                tolerance: 'pointer',
                update: function(event, ui) {
                    self.updateGroupOrder();
                }
            });
        },

        // Update condition order after drag and drop
        updateConditionOrder: function() {
            var self = this;
            
            $('.rule-group').each(function() {
                var groupId = $(this).data('group-id');
                var group = self.rules.groups.find(function(g) { return g.id === groupId; });
                
                if (group) {
                    var newOrder = [];
                    $(this).find('.condition-row').each(function() {
                        var conditionId = $(this).data('condition-id');
                        var condition = group.conditions.find(function(c) { return c.id === conditionId; });
                        if (condition) {
                            newOrder.push(condition);
                        }
                    });
                    group.conditions = newOrder;
                }
            });
            
            this.updateRulesData();
            this.updatePreview();
        },

        // Update group order after drag and drop
        updateGroupOrder: function() {
            var self = this;
            var newOrder = [];
            
            $('#vira-rule-groups .rule-group').each(function() {
                var groupId = $(this).data('group-id');
                var group = self.rules.groups.find(function(g) { return g.id === groupId; });
                if (group) {
                    newOrder.push(group);
                }
            });
            
            this.rules.groups = newOrder;
            this.updateRulesData();
            this.updatePreview();
        },

        // Validate individual condition
        validateCondition: function($input) {
            var conditionRow = $input.closest('.condition-row');
            var conditionType = conditionRow.find('.condition-type').val();
            var conditionOperator = conditionRow.find('.condition-operator').val();
            var conditionValue = $input.val();
            
            // Remove existing validation classes
            $input.removeClass('validation-error validation-warning validation-success');
            conditionRow.find('.validation-message').remove();
            
            var validation = this.validateConditionValue(conditionType, conditionOperator, conditionValue);
            
            if (validation.status === 'error') {
                $input.addClass('validation-error');
                $input.after('<div class="validation-message error">' + validation.message + '</div>');
            } else if (validation.status === 'warning') {
                $input.addClass('validation-warning');
                $input.after('<div class="validation-message warning">' + validation.message + '</div>');
            } else if (validation.status === 'success') {
                $input.addClass('validation-success');
            }
            
            return validation.status !== 'error';
        },

        // Validate condition value
        validateConditionValue: function(type, operator, value) {
            if (!value || value.trim() === '') {
                return {
                    status: 'error',
                    message: 'Value is required'
                };
            }
            
            switch (type) {
                case 'url_pattern':
                    if (operator === 'regex') {
                        try {
                            new RegExp(value);
                            return { status: 'success' };
                        } catch (e) {
                            return {
                                status: 'error',
                                message: 'Invalid regular expression: ' + e.message
                            };
                        }
                    }
                    break;
                    
                case 'custom_php':
                    if (value.includes('<?php') || value.includes('?>')) {
                        return {
                            status: 'warning',
                            message: 'PHP tags are not needed and will be added automatically'
                        };
                    }
                    
                    // Basic PHP syntax validation
                    var dangerousFunctions = ['exec', 'system', 'shell_exec', 'file_get_contents', 'file_put_contents', 'eval'];
                    for (var i = 0; i < dangerousFunctions.length; i++) {
                        if (value.includes(dangerousFunctions[i])) {
                            return {
                                status: 'error',
                                message: 'Dangerous function "' + dangerousFunctions[i] + '" is not allowed'
                            };
                        }
                    }
                    break;
                    
                case 'date_range':
                    if (operator === 'between') {
                        // For date ranges, value should be an object with start and end
                        return { status: 'success' };
                    } else {
                        // Validate single date
                        var date = new Date(value);
                        if (isNaN(date.getTime())) {
                            return {
                                status: 'error',
                                message: 'Invalid date format'
                            };
                        }
                    }
                    break;
            }
            
            return { status: 'success' };
        },

        // Validate all rules
        validateAllRules: function() {
            var isValid = true;
            var self = this;
            
            $('.condition-value').each(function() {
                if (!self.validateCondition($(this))) {
                    isValid = false;
                }
            });
            
            // Check for empty groups
            this.rules.groups.forEach(function(group) {
                if (group.conditions.length === 0) {
                    isValid = false;
                }
            });
            
            return isValid;
        },

        // Check conditional logic rules
        checkConditionalLogic: function() {
            var self = this;
            var snippetId = $('#snippet-id').val();
            
            // Show loading state
            var checkButton = $('#check-conditional-logic');
            var originalText = checkButton.html();
            checkButton.html('<span class="dashicons dashicons-update spin"></span> Checking...').prop('disabled', true);
            
            // Update rules data before checking
            this.updateRulesData();
            
            // Validate rules first
            if (!this.validateAllRules()) {
                this.showTestResults({
                    success: false,
                    message: 'Please fix validation errors before checking rules.',
                    errors: ['One or more conditions have validation errors']
                });
                checkButton.html(originalText).prop('disabled', false);
                return;
            }
            
            // If no snippet ID (new snippet), show preview only
            if (!snippetId || snippetId === '0') {
                this.showTestResults({
                    success: true,
                    message: 'Rules validation passed. Save the snippet to test actual evaluation.',
                    preview_only: true,
                    rules: this.rules
                });
                checkButton.html(originalText).prop('disabled', false);
                return;
            }
            
            // Send AJAX request to test rules
            $.post(viraCode.ajaxUrl, {
                action: 'vira_code_test_conditional_logic',
                nonce: viraCode.nonce,
                snippet_id: snippetId,
                rules: JSON.stringify(this.rules)
            }).done(function(response) {
                if (response.success) {
                    self.showTestResults(response.data);
                } else {
                    self.showTestResults({
                        success: false,
                        message: response.data.message || 'Test failed',
                        errors: response.data.errors || []
                    });
                }
            }).fail(function(xhr, status, error) {
                self.showTestResults({
                    success: false,
                    message: 'Request failed: ' + error,
                    errors: ['Network error or server unavailable']
                });
            }).always(function() {
                checkButton.html(originalText).prop('disabled', false);
            });
        },

        // Show test results
        showTestResults: function(results) {
            var resultsContainer = $('#conditional-test-results');
            var resultsContent = resultsContainer.find('.test-results-content');
            
            var html = '';
            
            if (results.success) {
                html += '<div class="notice notice-success inline">';
                html += '<p><strong>✓ ' + (results.message || 'Rules check passed') + '</strong></p>';
                
                if (results.preview_only) {
                    html += '<p><em>This is a preview for a new snippet. Save the snippet to test actual evaluation.</em></p>';
                } else if (results.evaluation_result !== undefined) {
                    html += '<p><strong>Evaluation Result:</strong> ' + (results.evaluation_result ? 'PASS (snippet would execute)' : 'FAIL (snippet would not execute)') + '</p>';
                    html += '<p><strong>Evaluation Time:</strong> ' + (results.evaluation_time ? (results.evaluation_time * 1000).toFixed(2) + 'ms' : 'N/A') + '</p>';
                }
                
                html += '</div>';
                
                // Show condition results if available
                if (results.condition_results && Object.keys(results.condition_results).length > 0) {
                    html += '<div class="condition-results">';
                    html += '<h5>Individual Condition Results:</h5>';
                    html += '<ul>';
                    
                    for (var conditionKey in results.condition_results) {
                        var conditionResult = results.condition_results[conditionKey];
                        var status = conditionResult.result ? '✓' : '✗';
                        var statusClass = conditionResult.result ? 'success' : 'error';
                        
                        html += '<li class="condition-result ' + statusClass + '">';
                        html += '<strong>' + status + ' ' + conditionResult.condition.type + '</strong>: ';
                        html += conditionResult.condition.operator + ' "' + conditionResult.condition.value + '"';
                        
                        if (conditionResult.evaluation_time) {
                            html += ' <em>(' + (conditionResult.evaluation_time * 1000).toFixed(2) + 'ms)</em>';
                        }
                        
                        if (conditionResult.error) {
                            html += '<br><span class="error-message">Error: ' + conditionResult.error + '</span>';
                        }
                        
                        html += '</li>';
                    }
                    
                    html += '</ul>';
                    html += '</div>';
                }
                
                // Show warnings if any
                if (results.warnings && results.warnings.length > 0) {
                    html += '<div class="notice notice-warning inline">';
                    html += '<p><strong>Warnings:</strong></p>';
                    html += '<ul>';
                    results.warnings.forEach(function(warning) {
                        html += '<li>' + warning + '</li>';
                    });
                    html += '</ul>';
                    html += '</div>';
                }
                
            } else {
                html += '<div class="notice notice-error inline">';
                html += '<p><strong>✗ ' + (results.message || 'Rules check failed') + '</strong></p>';
                
                if (results.errors && results.errors.length > 0) {
                    html += '<p><strong>Errors:</strong></p>';
                    html += '<ul>';
                    results.errors.forEach(function(error) {
                        html += '<li>' + error + '</li>';
                    });
                    html += '</ul>';
                }
                
                html += '</div>';
            }
            
            resultsContent.html(html);
            resultsContainer.slideDown();
            
            // Scroll to results
            $('html, body').animate({
                scrollTop: resultsContainer.offset().top - 100
            }, 500);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        // Only initialize on snippet editor page
        if ($('#vira-snippet-form').length) {
            try {
                ViraConditionalLogic.init();
            } catch (error) {
                console.error('Conditional Logic initialization error:', error);
            }
        }
    });

})(jQuery);