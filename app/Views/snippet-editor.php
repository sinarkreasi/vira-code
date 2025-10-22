<?php
/**
 * Snippet Editor View
 *
 * @package ViraCode
 */

if (!defined("ABSPATH")) {
    exit();
}

// Check if this is from library (pre-fill from URL parameters)
$from_library = isset($_GET["library"]) && $_GET["library"] === "1";

$snippet_id = $snippet ? $snippet["id"] : 0;
$title = $snippet
    ? $snippet["title"]
    : ($from_library && isset($_GET["title"])
        ? sanitize_text_field(wp_unslash($_GET["title"]))
        : "");
$description = $snippet
    ? $snippet["description"]
    : ($from_library && isset($_GET["description"])
        ? sanitize_textarea_field(wp_unslash($_GET["description"]))
        : "");
$code = $snippet
    ? $snippet["code"]
    : ($from_library && isset($_GET["code"])
        ? wp_unslash($_GET["code"])
        : "");
$type = $snippet
    ? $snippet["type"]
    : ($from_library && isset($_GET["type"])
        ? sanitize_text_field(wp_unslash($_GET["type"]))
        : "php");
$scope = $snippet
    ? $snippet["scope"]
    : ($from_library && isset($_GET["scope"])
        ? sanitize_text_field(wp_unslash($_GET["scope"]))
        : "frontend");
$status = $snippet ? $snippet["status"] : "inactive";
$tags = $snippet
    ? $snippet["tags"]
    : ($from_library && isset($_GET["tags"])
        ? sanitize_text_field(wp_unslash($_GET["tags"]))
        : "");
$category = $snippet
    ? $snippet["category"]
    : ($from_library && isset($_GET["category"])
        ? sanitize_text_field(wp_unslash($_GET["category"]))
        : "");
$priority = $snippet ? $snippet["priority"] : 10;
$error_message =
    $snippet && isset($snippet["error_message"])
        ? $snippet["error_message"]
        : "";

// Load conditional logic rules if editing existing snippet
$conditional_logic_rules = [];
$conditional_logic_enabled = false;
if ($snippet && $snippet_id > 0) {
    $conditional_logic_model = new \ViraCode\Models\ConditionalLogicModel();
    $rules = $conditional_logic_model->getRules($snippet_id);
    if (!empty($rules)) {
        $conditional_logic_rules = $rules;
        $conditional_logic_enabled = true;
    }
}

$page_title = $is_new
    ? __("Add New Snippet", "vira-code")
    : __("Edit Snippet", "vira-code");
?>

<div class="wrap vira-code-admin vira-snippet-editor">
	<h1 class="wp-heading-inline">
		<?php echo esc_html($page_title); ?>
	</h1>
	<a href="<?php echo esc_url(
     admin_url("admin.php?page=vira-code"),
 ); ?>" class="page-title-action">
		<?php esc_html_e("Back to Snippets", "vira-code"); ?>
	</a>
	<hr class="wp-header-end">

	<?php if ($from_library): ?>
		<div class="notice notice-info">
			<p>
				<strong><?php esc_html_e(
        "Snippet Loaded from Library",
        "vira-code",
    ); ?></strong>
				<?php esc_html_e(
        "Review the code below and customize it as needed before saving.",
        "vira-code",
    ); ?>
			</p>
		</div>
	<?php endif; ?>

	<?php if (!empty($error_message)): ?>
		<div class="notice notice-error">
			<p>
				<strong><?php esc_html_e(
        "This snippet has an error:",
        "vira-code",
    ); ?></strong><br>
				<?php echo esc_html($error_message); ?>
			</p>
		</div>
	<?php endif; ?>

	<form id="vira-snippet-form" method="post" class="vira-snippet-form">
		<input type="hidden" name="snippet_id" id="snippet-id" value="<?php echo esc_attr($snippet_id); ?>">
		<input type="hidden" name="conditional_logic_rules" id="conditional-logic-rules" value="">
		<?php // Provide nonce for non-AJAX fallback ?>
		<input type="hidden" name="vira_code_nonce" id="vira-code-nonce" value="<?php echo esc_attr(wp_create_nonce(\ViraCode\vira_code_nonce_action())); ?>">

		<div class="vira-editor-main">
			<div class="vira-editor-content">
				<!-- Title Field -->
				<div class="vira-form-group">
					<label for="snippet-title" class="vira-label">
						<?php esc_html_e("Snippet Title", "vira-code"); ?>
						<span class="required">*</span>
					</label>
					<input
						type="text"
						id="snippet-title"
						name="title"
						class="regular-text"
						value="<?php echo esc_attr($title); ?>"
						placeholder="<?php esc_attr_e("Enter snippet title...", "vira-code"); ?>"
						required
					>
				</div>

				<!-- Description Field -->
				<div class="vira-form-group">
					<label for="snippet-description" class="vira-label">
						<?php esc_html_e("Description", "vira-code"); ?>
					</label>
					<textarea
						id="snippet-description"
						name="description"
						class="large-text"
						rows="3"
						placeholder="<?php esc_attr_e(
          "Optional description for this snippet...",
          "vira-code",
      ); ?>"
					><?php echo esc_textarea($description); ?></textarea>
				</div>

				<!-- Code Editor -->
				<div class="vira-form-group">
					<label for="snippet-code" class="vira-label">
						<?php esc_html_e("Code", "vira-code"); ?>
						<span class="required">*</span>
					</label>
					<div class="vira-code-editor-wrapper">
						<textarea
							id="snippet-code"
							name="code"
							class="vira-code-editor large-text code"
							rows="20"
						><?php echo esc_textarea($code); ?></textarea>
					</div>
					<p class="description">
						<?php printf(
          /* translators: %s: snippet type */
          esc_html__(
              "Enter your %s code. For PHP snippets, do not include opening/closing PHP tags.",
              "vira-code",
          ),
          '<span class="vira-current-type">' .
              esc_html(strtoupper($type)) .
              "</span>",
      ); ?>
					</p>
				</div>

				<!-- Tags Field -->
				<div class="vira-form-group">
					<label for="snippet-tags" class="vira-label">
						<?php esc_html_e("Tags", "vira-code"); ?>
					</label>
					<input
						type="text"
						id="snippet-tags"
						name="tags"
						class="regular-text"
						value="<?php echo esc_attr($tags); ?>"
						placeholder="<?php esc_attr_e("Comma-separated tags...", "vira-code"); ?>"
					>
					<p class="description"><?php esc_html_e(
         "Add tags to organize your snippets (comma-separated).",
         "vira-code",
     ); ?></p>
				</div>

				<!-- Category Field -->
				<div class="vira-form-group">
					<label for="snippet-category" class="vira-label">
						<?php esc_html_e("Category", "vira-code"); ?>
					</label>
					<input
						type="text"
						id="snippet-category"
						name="category"
						class="regular-text"
						value="<?php echo esc_attr($category); ?>"
						placeholder="<?php esc_attr_e(
          "e.g., Custom Functions, Styling, Scripts...",
          "vira-code",
      ); ?>"
					>
				</div>

				<!-- Conditional Logic Section (moved from sidebar) -->
				<div class="vira-form-group vira-conditional-logic-section">
					<h3 class="vira-section-title">
						<?php esc_html_e("Conditional Logic", "vira-code"); ?>
						<span class="vira-conditional-toggle">
							<input type="checkbox" id="conditional-logic-enabled" name="conditional_logic_enabled" value="1" <?php checked($conditional_logic_enabled); ?>>
							<label for="conditional-logic-enabled"><?php esc_html_e("Enable", "vira-code"); ?></label>
						</span>
					</h3>
					<div class="vira-conditional-content" style="display: <?php echo $conditional_logic_enabled ? 'block' : 'none'; ?>;">
						<div class="vira-conditional-rules">
							<div class="vira-rule-groups" id="vira-rule-groups">
								<!-- Rule groups will be dynamically added here -->
							</div>
							
							<div class="vira-conditional-actions">
								<button type="button" class="button button-secondary" id="add-condition-group">
									<span class="dashicons dashicons-plus-alt"></span>
									<?php esc_html_e("Add Condition Group", "vira-code"); ?>
								</button>
								<button type="button" class="button button-secondary" id="check-conditional-logic">
									<span class="dashicons dashicons-yes-alt"></span>
									<?php esc_html_e("Check Rules", "vira-code"); ?>
								</button>
								<button type="button" class="button button-secondary" id="import-rules">
									<span class="dashicons dashicons-upload"></span>
									<?php esc_html_e("Import Rules", "vira-code"); ?>
								</button>
								<button type="button" class="button button-secondary" id="export-rules">
									<span class="dashicons dashicons-download"></span>
									<?php esc_html_e("Export Rules", "vira-code"); ?>
								</button>
							</div>
						</div>
						
						<div class="vira-conditional-preview">
							<h4><?php esc_html_e("Rule Preview", "vira-code"); ?></h4>
							<div class="vira-rule-preview-content">
								<em><?php esc_html_e("No conditions set", "vira-code"); ?></em>
							</div>
						</div>

						<div class="vira-conditional-test-results" id="conditional-test-results" style="display: none;">
							<h4><?php esc_html_e("Test Results", "vira-code"); ?></h4>
							<div class="test-results-content"></div>
						</div>
					</div>
				</div>
			</div>

			<div class="vira-editor-sidebar">
				<!-- Publish Box -->
				<div class="vira-meta-box">
					<h3><?php esc_html_e("Publish", "vira-code"); ?></h3>
					<div class="vira-meta-box-content">
						<div class="vira-form-group">
							<label for="snippet-status" class="vira-label">
								<?php esc_html_e("Status", "vira-code"); ?>
							</label>
							<select id="snippet-status" name="status" class="widefat">
								<option value="active" <?php selected($status, "active"); ?>>
									<?php esc_html_e("Active", "vira-code"); ?>
								</option>
								<option value="inactive" <?php selected($status, "inactive"); ?>>
									<?php esc_html_e("Inactive", "vira-code"); ?>
								</option>
							</select>
						</div>

						<div class="vira-publish-actions">
							<button type="button" class="button button-primary button-large" id="vira-save-snippet">
								<span class="dashicons dashicons-saved"></span>
								<?php echo $is_new
            ? esc_html__("Create Snippet", "vira-code")
            : esc_html__("Update Snippet", "vira-code"); ?>
							</button>
							<button type="button" class="button button-secondary button-large" id="vira-test-snippet">
								<span class="dashicons dashicons-media-code"></span>
								<?php esc_html_e("Test Snippet", "vira-code"); ?>
							</button>
						</div>

						<?php if (!$is_new): ?>
							<div class="vira-snippet-info">
								<p class="vira-snippet-id">
									<strong><?php esc_html_e("ID:", "vira-code"); ?></strong> <?php echo esc_html(
    $snippet_id,
); ?>
								</p>
								<?php if ($snippet && isset($snippet["created_at"])): ?>
									<p class="vira-snippet-created">
										<strong><?php esc_html_e("Created:", "vira-code"); ?></strong><br>
										<?php echo esc_html(
              date_i18n(
                  get_option("date_format"),
                  strtotime($snippet["created_at"]),
              ),
          ); ?>
									</p>
								<?php endif; ?>
								<?php if ($snippet && isset($snippet["updated_at"])): ?>
									<p class="vira-snippet-updated">
										<strong><?php esc_html_e("Last Updated:", "vira-code"); ?></strong><br>
										<?php echo esc_html(
              date_i18n(
                  get_option("date_format"),
                  strtotime($snippet["updated_at"]),
              ),
          ); ?>
									</p>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<!-- Snippet Settings -->
				<div class="vira-meta-box">
					<h3><?php esc_html_e("Snippet Settings", "vira-code"); ?></h3>
					<div class="vira-meta-box-content">
						<div class="vira-form-group">
							<label for="snippet-type" class="vira-label">
								<?php esc_html_e("Snippet Type", "vira-code"); ?>
								<span class="required">*</span>
							</label>
							<select id="snippet-type" name="type" class="widefat" required>
								<?php foreach (
            \ViraCode\vira_code_snippet_types()
            as $type_key => $type_label
        ): ?>
									<option value="<?php echo esc_attr($type_key); ?>" <?php selected(
    $type,
    $type_key,
); ?>>
										<?php echo esc_html($type_label); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</div>

						<div class="vira-form-group">
							<label for="snippet-scope" class="vira-label">
								<?php esc_html_e("Execution Scope", "vira-code"); ?>
								<span class="required">*</span>
							</label>
							<select id="snippet-scope" name="scope" class="widefat" required>
								<?php foreach (
            \ViraCode\vira_code_snippet_scopes()
            as $scope_key => $scope_label
        ): ?>
									<option value="<?php echo esc_attr($scope_key); ?>" <?php selected(
    $scope,
    $scope_key,
); ?>>
										<?php echo esc_html($scope_label); ?>
									</option>
								<?php endforeach; ?>
							</select>
							<p class="description">
								<?php esc_html_e("Choose where this snippet should execute.", "vira-code"); ?>
							</p>
						</div>

						<div class="vira-form-group">
							<label for="snippet-priority" class="vira-label">
								<?php esc_html_e("Priority", "vira-code"); ?>
							</label>
							<input
								type="number"
								id="snippet-priority"
								name="priority"
								class="small-text"
								value="<?php echo esc_attr($priority); ?>"
								min="1"
								max="999"
							>
							<p class="description">
								<?php esc_html_e("Lower numbers execute first. Default: 10", "vira-code"); ?>
							</p>
						</div>
					</div>
				</div>



				<!-- Help Box -->
				<div class="vira-meta-box vira-help-box">
					<h3><?php esc_html_e("Need Help?", "vira-code"); ?></h3>
					<div class="vira-meta-box-content">
						<ul class="vira-help-list">
							<li>
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e("PHP snippets execute server-side", "vira-code"); ?>
							</li>
							<li>
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e("JS/CSS snippets output inline", "vira-code"); ?>
							</li>
							<li>
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e('Use "Test Snippet" before activating', "vira-code"); ?>
							</li>
							<li>
								<span class="dashicons dashicons-info"></span>
								<?php esc_html_e("Safe Mode disables all snippets", "vira-code"); ?>
							</li>
						</ul>
					</div>
				</div>
			</div>
		</div>
	</form>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
	// Simple and reliable CodeMirror initialization
	if (typeof wp !== 'undefined' && wp.codeEditor && $('#snippet-code').length) {
		console.log('[Vira Code] Initializing CodeMirror...');

		var editorSettings = wp.codeEditor.defaultSettings ? _.clone(wp.codeEditor.defaultSettings) : {};
		editorSettings.codemirror = _.extend({}, editorSettings.codemirror, {
			mode: 'application/x-httpd-php',
			lineNumbers: true,
			lineWrapping: true,
			indentUnit: 4,
			indentWithTabs: true,
			autoCloseBrackets: true,
			matchBrackets: true
		});

		var editor = wp.codeEditor.initialize($('#snippet-code'), editorSettings);

		if (editor && editor.codemirror) {
			window.viraCodeEditor = editor;
			console.log('[Vira Code] CodeMirror initialized successfully');

			// Update mode based on snippet type
			$('#snippet-type').on('change', function() {
				var type = $(this).val();
				var mode = 'application/x-httpd-php';

				switch(type) {
					case 'js': mode = 'javascript'; break;
					case 'css': mode = 'css'; break;
					case 'html': mode = 'htmlmixed'; break;
				}

				editor.codemirror.setOption('mode', mode);
				$('.vira-current-type').text(type.toUpperCase());
			});

			// Add keyboard shortcuts
			editor.codemirror.setOption('extraKeys', {
				'Ctrl-Space': 'autocomplete',
				'Ctrl-S': function(cm) {
					cm.save();
					console.log('[Vira Code] Ctrl+S triggered, clicking save button');
					$('#vira-save-snippet').click();
				},
				'Cmd-S': function(cm) {
					cm.save();
					console.log('[Vira Code] Cmd+S triggered, clicking save button');
					$('#vira-save-snippet').click();
				}
			});

			// Sync content before form submission - this is now handled by the button click in admin.js
			// Keep this as a fallback for direct form submissions
			$('#vira-snippet-form').on('submit', function(e) {
				if (window.viraCodeEditor && window.viraCodeEditor.codemirror) {
					window.viraCodeEditor.codemirror.save();
					console.log('[Vira Code] Content synced on form submit fallback');
				}
			});

			// Helper: send AJAX request to validate snippet (fallback)
			function viraCodeFallbackValidate(code, type) {
				var ajaxUrl = typeof viraCode !== 'undefined' ? viraCode.ajaxUrl : '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
				var nonce = typeof viraCode !== 'undefined' ? viraCode.nonce : '<?php echo esc_js(wp_create_nonce(\ViraCode\vira_code_nonce_action())); ?>';
				$.post(ajaxUrl, {
					action: 'vira_code_validate_snippet',
					nonce: nonce,
					code: code,
					type: type
				}).done(function(response){
					if (response && response.success) {
						alert(response.data.message || 'Validation passed');
					} else {
						var msg = (response && response.data && response.data.error) ? response.data.error : 'Validation failed';
						alert(msg);
					}
				}).fail(function(){
					alert('Validation request failed. Check console for details.');
				});
			}

			// Helper: send AJAX request to save snippet (fallback)
			function viraCodeFallbackSave() {
				var ajaxUrl = typeof viraCode !== 'undefined' ? viraCode.ajaxUrl : '<?php echo esc_js(admin_url("admin-ajax.php")); ?>';
				var nonce = typeof viraCode !== 'undefined' ? viraCode.nonce : '<?php echo esc_js(wp_create_nonce(\ViraCode\vira_code_nonce_action())); ?>';
				var data = {
					action: 'vira_code_save_snippet',
					nonce: nonce,
					id: $('#snippet-id').val(),
					title: $('#snippet-title').val(),
					description: $('#snippet-description').val(),
					code: $('#snippet-code').val(),
					type: $('#snippet-type').val(),
					scope: $('#snippet-scope').val(),
					status: $('#snippet-status').val(),
					tags: $('#snippet-tags').val(),
					category: $('#snippet-category').val(),
					priority: $('#snippet-priority').val()
				};
				$.post(ajaxUrl, data).done(function(response){
					if (response && response.success) {
						alert(response.data.message || 'Snippet saved');
						if (response.data.snippet_id) {
							$('#snippet-id').val(response.data.snippet_id);
							setTimeout(function(){ window.location.reload(); }, 800);
						}
					} else {
						alert((response && response.data && response.data.message) ? response.data.message : 'Save failed');
					}
				}).fail(function(){
					alert('Save request failed. Check console for details.');
				});
			}

			// Make Save button resilient: try Editor -> Admin -> fallback
			$('#vira-save-snippet').on('click', function(e){
				e.preventDefault();
				// Ensure CodeMirror content is saved back to textarea
				if (window.viraCodeEditor && window.viraCodeEditor.codemirror) {
					try { window.viraCodeEditor.codemirror.save(); } catch (err) { console.warn(err); }
				}

				if (window.ViraCodeEditor && typeof ViraCodeEditor.saveSnippet === 'function') {
					ViraCodeEditor.saveSnippet();
					return;
				}
				if (window.ViraCodeAdmin && typeof ViraCodeAdmin.saveSnippet === 'function') {
					ViraCodeAdmin.saveSnippet(e);
					return;
				}
				// Last resort: perform fallback AJAX save
				console.warn('[Vira Code] Falling back to inline AJAX save');
				viraCodeFallbackSave();
			});

			// Make Test button resilient: try Admin -> Editor -> fallback
			$('#vira-test-snippet').on('click', function(e){
				e.preventDefault();
				if (window.viraCodeEditor && window.viraCodeEditor.codemirror) {
					try { window.viraCodeEditor.codemirror.save(); } catch (err) { console.warn(err); }
				}

				if (window.ViraCodeAdmin && typeof ViraCodeAdmin.testSnippet === 'function') {
					ViraCodeAdmin.testSnippet(e);
					return;
				}
				if (window.ViraCodeEditor && typeof ViraCodeEditor.handleTestClick === 'function') {
					ViraCodeEditor.handleTestClick(e);
					return;
				}
				// Fallback validate via AJAX
				console.warn('[Vira Code] Falling back to inline AJAX validate');
				viraCodeFallbackValidate($('#snippet-code').val(), $('#snippet-type').val());
			});
		}
	} else {
		console.error('[Vira Code] CodeMirror requirements not available');
	}
});
</script>

<script type="text/javascript">
// Pass conditional logic data to JavaScript
<?php if (!empty($conditional_logic_rules)): ?>
window.viraConditionalLogicData = <?php echo wp_json_encode($conditional_logic_rules); ?>;
<?php endif; ?>

// Conditional Logic Fallback
jQuery(document).ready(function($) {
    // Fallback: If ViraConditionalLogic doesn't exist, create a minimal version
    if (typeof window.ViraConditionalLogic === 'undefined') {
        window.ViraConditionalLogic = {
            init: function() {
                this.bindBasicEvents();
            },
            
            bindBasicEvents: function() {
                var self = this;
                
                // Toggle conditional logic
                $('#conditional-logic-enabled').on('change', function() {
                    if ($(this).is(':checked')) {
                        $('.vira-conditional-content').slideDown();
                        // Add a default condition group if none exist
                        if ($('.rule-group').length === 0) {
                            $('#add-condition-group').click();
                        }
                    } else {
                        $('.vira-conditional-content').slideUp();
                    }
                    updateConditionalRules();
                });
                
                // Make sure rules are updated before form submission
                $('#vira-snippet-form').on('submit', function() {
                    updateConditionalRules();
                });
                
                // Also update when save button is clicked
                $('#vira-save-snippet').on('click', function() {
                    updateConditionalRules();
                });
                
                // Working check functionality
                $('#check-conditional-logic').on('click', function() {
                    var snippetId = $('#snippet-id').val();
                    if (!snippetId || snippetId === '0') {
                        alert('Please save the snippet first before testing rules.');
                        return;
                    }
                    
                    var button = $(this);
                    var originalText = button.html();
                    button.html('<span class="dashicons dashicons-update"></span> Checking...').prop('disabled', true);
                    
                    $.post(ajaxurl, {
                        action: 'vira_code_test_conditional_logic',
                        nonce: '<?php echo wp_create_nonce(\ViraCode\vira_code_nonce_action()); ?>',
                        snippet_id: snippetId
                    }).done(function(response) {
                        if (response.success) {
                            var result = response.data.evaluation_result ? 'PASS' : 'FAIL';
                            alert('Test completed! Result: ' + result);
                        } else {
                            alert('Test failed: ' + (response.data.message || 'Unknown error'));
                        }
                    }).fail(function() {
                        alert('Request failed. Please try again.');
                    }).always(function() {
                        button.html(originalText).prop('disabled', false);
                    });
                });
                
                // Add Condition Group functionality
                $('#add-condition-group').on('click', function() {
                    var groupId = 'group_' + Date.now();
                    var groupHtml = '<div class="rule-group" data-group-id="' + groupId + '" style="border: 1px solid #ddd; margin: 10px 0; padding: 15px; background: #f9f9f9; border-radius: 4px;">';
                    
                    // Group header
                    groupHtml += '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 10px; border-bottom: 1px solid #ddd;">';
                    groupHtml += '<h4 style="margin: 0;">Condition Group</h4>';
                    groupHtml += '<div>';
                    groupHtml += '<select class="group-logic" style="margin-right: 10px;">';
                    groupHtml += '<option value="AND">AND (All conditions must match)</option>';
                    groupHtml += '<option value="OR">OR (Any condition can match)</option>';
                    groupHtml += '</select>';
                    groupHtml += '<button type="button" class="button button-small remove-group" style="background: #dc3232; color: white;">Remove Group</button>';
                    groupHtml += '</div></div>';
                    
                    // Conditions container
                    groupHtml += '<div class="conditions-container">';
                    groupHtml += self.createConditionRow(groupId, 'condition_' + Date.now());
                    groupHtml += '</div>';
                    
                    // Add condition button
                    groupHtml += '<div style="margin-top: 10px;">';
                    groupHtml += '<button type="button" class="button button-secondary button-small add-condition" data-group="' + groupId + '">Add Condition</button>';
                    groupHtml += '</div>';
                    
                    groupHtml += '</div>';
                    $('#vira-rule-groups').append(groupHtml);
                    
                    // Bind events for new group
                    self.bindConditionEvents();
                    updateConditionalRules();
                });
                
                // Function to create condition row
                this.createConditionRow = function(groupId, conditionId) {
                    var html = '<div class="condition-row" data-condition-id="' + conditionId + '" style="display: flex; gap: 10px; margin-bottom: 10px; padding: 10px; background: white; border: 1px solid #ddd; border-radius: 3px; align-items: center;">';
                    
                    // Condition type
                    html += '<select class="condition-type" style="flex: 1;">';
                    html += '<option value="page_type">Page Type</option>';
                    html += '<option value="url_pattern">URL Pattern</option>';
                    html += '<option value="user_role">User Role</option>';
                    html += '<option value="device_type">Device Type</option>';
                    html += '</select>';
                    
                    // Operator
                    html += '<select class="condition-operator" style="flex: 1;">';
                    html += '<option value="equals">Equals</option>';
                    html += '<option value="not_equals">Not Equals</option>';
                    html += '<option value="contains">Contains</option>';
                    html += '</select>';
                    
                    // Value
                    html += '<input type="text" class="condition-value" placeholder="Enter value..." style="flex: 2;">';
                    
                    // Remove button
                    html += '<button type="button" class="button button-small remove-condition" style="background: #dc3232; color: white;">Remove</button>';
                    
                    html += '</div>';
                    return html;
                };
                
                // Function to bind condition events
                this.bindConditionEvents = function() {
                    // Add condition
                    $('.add-condition').off('click').on('click', function() {
                        var groupId = $(this).data('group');
                        var conditionId = 'condition_' + Date.now();
                        var conditionHtml = self.createConditionRow(groupId, conditionId);
                        $(this).closest('.rule-group').find('.conditions-container').append(conditionHtml);
                        self.bindConditionEvents();
                        updateConditionalRules();
                    });
                    
                    // Remove condition
                    $('.remove-condition').off('click').on('click', function() {
                        $(this).closest('.condition-row').remove();
                        updateConditionalRules();
                    });
                    
                    // Remove group
                    $('.remove-group').off('click').on('click', function() {
                        $(this).closest('.rule-group').remove();
                        updateConditionalRules();
                    });
                    
                    // Update rules when values change
                    $('.condition-type, .condition-operator, .condition-value, .group-logic').off('change input').on('change input', function() {
                        updateConditionalRules();
                    });
                };
                
                // Function to update conditional rules data
                window.updateConditionalRules = function() {
                    var rules = {
                        enabled: $('#conditional-logic-enabled').is(':checked'),
                        logic: 'AND',
                        groups: []
                    };
                    
                    $('.rule-group').each(function() {
                        var group = {
                            logic: $(this).find('.group-logic').val() || 'AND',
                            conditions: []
                        };
                        
                        $(this).find('.condition-row').each(function() {
                            var condition = {
                                type: $(this).find('.condition-type').val(),
                                operator: $(this).find('.condition-operator').val(),
                                value: $(this).find('.condition-value').val()
                            };
                            
                            if (condition.type && condition.operator && condition.value) {
                                group.conditions.push(condition);
                            }
                        });
                        
                        if (group.conditions.length > 0) {
                            rules.groups.push(group);
                        }
                    });
                    
                    // Update hidden field for form submission
                    $('#conditional-logic-rules').val(JSON.stringify(rules));
                    
                    // Update preview
                    var preview = $('.vira-rule-preview-content');
                    if (rules.groups.length === 0) {
                        preview.html('<em>No conditions set</em>');
                    } else {
                        var previewHtml = '';
                        rules.groups.forEach(function(group, groupIndex) {
                            if (groupIndex > 0) previewHtml += '<div style="text-align: center; margin: 5px 0; font-weight: bold;">AND</div>';
                            previewHtml += '<div style="padding: 8px; background: #f0f0f0; border-left: 3px solid #0073aa; margin: 5px 0;">';
                            previewHtml += '<strong>Group ' + (groupIndex + 1) + ' (' + group.logic + '):</strong><br>';
                            group.conditions.forEach(function(condition, conditionIndex) {
                                if (conditionIndex > 0) previewHtml += ' <em>' + group.logic + '</em> ';
                                previewHtml += condition.type.replace('_', ' ') + ' ' + condition.operator.replace('_', ' ') + ' "' + condition.value + '"';
                            });
                            previewHtml += '</div>';
                        });
                        preview.html(previewHtml);
                    }
                    
                };
                
                // Import Rules functionality
                $('#import-rules').on('click', function() {
                    alert('Import functionality: Please prepare a JSON file with your conditional logic rules and contact your administrator for import assistance.');
                });
                
                // Export Rules functionality
                $('#export-rules').on('click', function() {
                    var snippetId = $('#snippet-id').val();
                    var snippetTitle = $('#snippet-title').val() || 'Untitled Snippet';
                    
                    // Create a basic export object
                    var exportData = {
                        snippet_id: snippetId,
                        snippet_title: snippetTitle,
                        rules: {
                            enabled: $('#conditional-logic-enabled').is(':checked'),
                            groups: []
                        },
                        exported_at: new Date().toISOString()
                    };
                    
                    // Convert to JSON and download
                    var dataStr = JSON.stringify(exportData, null, 2);
                    var dataBlob = new Blob([dataStr], {type: 'application/json'});
                    var link = document.createElement('a');
                    link.href = URL.createObjectURL(dataBlob);
                    link.download = 'conditional-logic-rules-' + snippetId + '.json';
                    link.click();
                });

            }
        };
        
        // Initialize the fallback
        window.ViraConditionalLogic.init();
    }
});
</script>
