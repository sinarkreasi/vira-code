<?php
/**
 * Library View
 *
 * @package ViraCode
 */

if (!defined("ABSPATH")) {
    exit();
} ?>

<div class="wrap vira-code-admin vira-library-page">
	<h1 class="wp-heading-inline">
		<?php esc_html_e("Snippet Library", "vira-code"); ?>
	</h1>
	<a href="<?php echo esc_url(
     admin_url("admin.php?page=vira-code"),
 ); ?>" class="page-title-action">
		<?php esc_html_e("Back to Snippets", "vira-code"); ?>
	</a>
	<hr class="wp-header-end">

	<div class="vira-library-intro">
		<p class="description">
			<?php esc_html_e(
       "Browse our collection of ready-to-use code snippets. Click on any snippet to view details and add it to your site with one click.",
       "vira-code",
   ); ?>
		</p>

		<?php
  // Check if using file-based library
  $library_storage = new \ViraCode\Services\LibraryFileStorageService();
  $using_file_storage =
      $library_storage->isAvailable() &&
      !empty($library_storage->getAllSnippets());
  $has_hardcoded = !empty($snippets) && isset($snippets[0]['source']) && $snippets[0]['source'] === 'hardcoded';
  ?>

		<div class="vira-library-status">
			<?php if ($using_file_storage): ?>
				<div class="notice notice-success inline">
					<p>
						<span class="dashicons dashicons-yes-alt"></span>
						<?php esc_html_e(
          "Library is using optimized file-based storage.",
          "vira-code",
      ); ?>
						<button type="button" class="button button-small" id="vira-library-stats-btn">
							<?php esc_html_e("View Stats", "vira-code"); ?>
						</button>
					</p>
				</div>
			<?php elseif ($has_hardcoded): ?>
				<div class="notice notice-warning inline">
					<p>
						<span class="dashicons dashicons-warning"></span>
						<?php esc_html_e("Library is using legacy hardcoded storage.", "vira-code"); ?>
						<?php if ($library_storage->isAvailable()): ?>
							<button type="button" class="button button-primary button-small" id="vira-migrate-library-btn">
								<?php esc_html_e("Migrate to File Storage", "vira-code"); ?>
							</button>
							<span class="description"><?php esc_html_e("(Recommended for better performance)", "vira-code"); ?></span>
						<?php else: ?>
							<span class="description"><?php esc_html_e(
           "File storage not available - check directory permissions.",
           "vira-code",
       ); ?></span>
						<?php endif; ?>
					</p>
				</div>
			<?php else: ?>
				<div class="notice notice-info inline">
					<p>
						<span class="dashicons dashicons-info"></span>
						<?php esc_html_e("No library snippets available.", "vira-code"); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<?php if (empty($snippets)): ?>
		<div class="vira-empty-state">
			<div class="vira-empty-icon">
				<span class="dashicons dashicons-book-alt"></span>
			</div>
			<h2><?php esc_html_e("No Library Snippets Available", "vira-code"); ?></h2>
			<p><?php esc_html_e(
       "Library snippets will appear here when available.",
       "vira-code",
   ); ?></p>
		</div>
	<?php else: ?>
		<!-- Filter Bar -->
		<div class="vira-library-filters">
			<div class="vira-filter-group">
				<label for="vira-filter-category"><?php esc_html_e(
        "Category:",
        "vira-code",
    ); ?></label>
				<select id="vira-filter-category" class="vira-filter-select">
					<option value=""><?php esc_html_e("All Categories", "vira-code"); ?></option>
					<?php
     $categories = array_unique(array_column($snippets, "category"));
     foreach ($categories as $category): ?>
						<option value="<?php echo esc_attr(
          strtolower($category),
      ); ?>"><?php echo esc_html($category); ?></option>
					<?php endforeach;
     ?>
				</select>
			</div>

			<div class="vira-filter-group">
				<label for="vira-filter-search"><?php esc_html_e(
        "Search:",
        "vira-code",
    ); ?></label>
				<input type="text" id="vira-filter-search" class="vira-filter-input" placeholder="<?php esc_attr_e(
        "Search snippets...",
        "vira-code",
    ); ?>">
			</div>
		</div>

		<!-- Snippets Grid -->
		<div class="vira-library-grid">
			<?php foreach ($snippets as $snippet): ?>
				<div class="vira-library-card" 
					 data-category="<?php echo esc_attr(strtolower($snippet["category"])); ?>" 
					 data-tags="<?php echo esc_attr(strtolower(is_array($snippet["tags"]) ? implode(", ", $snippet["tags"]) : $snippet["tags"])); ?>"
					 data-source="<?php echo esc_attr($snippet['source'] ?? 'unknown'); ?>">
					<div class="vira-card-header">
						<div class="vira-card-icon">
							<span class="dashicons <?php echo esc_attr($snippet["icon"]); ?>"></span>
						</div>
						<div class="vira-card-meta">
							<span class="vira-card-category"><?php echo esc_html(
           $snippet["category"],
       ); ?></span>
							<span class="vira-card-type vira-badge vira-badge-<?php echo esc_attr(
           $snippet["type"],
       ); ?>">
								<?php echo esc_html(strtoupper($snippet["type"])); ?>
							</span>
						</div>
					</div>

					<div class="vira-card-body">
						<h3 class="vira-card-title"><?php echo esc_html(
          $snippet["title"] ?? $snippet["name"],
      ); ?></h3>
						<p class="vira-card-description"><?php echo esc_html(
          $snippet["description"],
      ); ?></p>

						<?php if (!empty($snippet["tags"])): ?>
							<div class="vira-card-tags">
								<?php
        $tags = is_array($snippet["tags"])
            ? $snippet["tags"]
            : explode(",", $snippet["tags"]);
        foreach ($tags as $tag):
            $tag = trim($tag); ?>
									<span class="vira-tag"><?php echo esc_html($tag); ?></span>
								<?php
        endforeach;
        ?>
							</div>
						<?php endif; ?>
						
						<?php if (isset($snippet['source'])): ?>
							<div class="vira-storage-type-indicator vira-storage-type-<?php echo esc_attr($snippet['source']); ?>">
								<?php if ($snippet['source'] === 'file_storage'): ?>
									<span class="dashicons dashicons-media-document"></span>
									<?php esc_html_e('File Storage', 'vira-code'); ?>
								<?php else: ?>
									<span class="dashicons dashicons-database"></span>
									<?php esc_html_e('Legacy Code', 'vira-code'); ?>
								<?php endif; ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="vira-card-footer">
						<button type="button"
							class="button button-secondary vira-view-snippet-btn"
							data-snippet-id="<?php echo esc_attr($snippet["id"] ?? $snippet["slug"]); ?>"
							data-snippet='<?php echo esc_attr(wp_json_encode($snippet)); ?>'>
							<span class="dashicons dashicons-visibility"></span>
							<?php esc_html_e("View Code", "vira-code"); ?>
						</button>
						<button type="button"
							class="button button-primary vira-use-snippet-btn"
							data-snippet-id="<?php echo esc_attr($snippet["id"] ?? $snippet["slug"]); ?>"
							data-snippet='<?php echo esc_attr(wp_json_encode($snippet)); ?>'>
							<span class="dashicons dashicons-plus-alt"></span>
							<?php esc_html_e("Use Snippet", "vira-code"); ?>
						</button>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

<!-- Modal for Viewing Snippet Code -->
<div id="vira-snippet-modal" class="vira-modal" style="display: none;">
	<div class="vira-modal-overlay"></div>
	<div class="vira-modal-content">
		<div class="vira-modal-header">
			<h2 id="vira-modal-title"><?php esc_html_e(
       "Snippet Details",
       "vira-code",
   ); ?></h2>
			<button type="button" class="vira-modal-close">
				<span class="dashicons dashicons-no-alt"></span>
			</button>
		</div>
		<div class="vira-modal-body">
			<div class="vira-modal-info">
				<p id="vira-modal-description"></p>
				<div class="vira-modal-meta">
					<span><strong><?php esc_html_e(
         "Type:",
         "vira-code",
     ); ?></strong> <span id="vira-modal-type"></span></span>
					<span><strong><?php esc_html_e(
         "Scope:",
         "vira-code",
     ); ?></strong> <span id="vira-modal-scope"></span></span>
					<span><strong><?php esc_html_e(
         "Category:",
         "vira-code",
     ); ?></strong> <span id="vira-modal-category"></span></span>
				</div>
			</div>
			<div class="vira-modal-code">
				<div class="vira-code-header">
					<strong><?php esc_html_e("Code:", "vira-code"); ?></strong>
					<button type="button" class="button button-small vira-copy-code-btn">
						<span class="dashicons dashicons-clipboard"></span>
						<?php esc_html_e("Copy Code", "vira-code"); ?>
					</button>
				</div>
				<pre id="vira-modal-code"></pre>
			</div>
		</div>
		<div class="vira-modal-footer">
			<button type="button" class="button button-secondary vira-modal-close-btn">
				<?php esc_html_e("Close", "vira-code"); ?>
			</button>
			<button type="button" class="button button-primary vira-modal-use-btn">
				<span class="dashicons dashicons-plus-alt"></span>
				<?php esc_html_e("Use This Snippet", "vira-code"); ?>
			</button>
		</div>
	</div>
</div>
<script type="text/javascript">
jQuery(document).ready(function($) {
	const ajaxUrl = "<?php echo esc_url(admin_url("admin-ajax.php")); ?>";
	const viraNonce = "<?php echo esc_js(
     wp_create_nonce(\ViraCode\vira_code_nonce_action()),
 ); ?>";

	// Library migration
	$('#vira-migrate-library-btn').on('click', function() {
		const button = $(this);
		const originalText = button.text();

		if (!confirm('<?php esc_html_e(
      "Are you sure you want to migrate the library to file storage? This will create files in wp-content/vira-cache/library/",
      "vira-code",
  ); ?>')) {
			return;
		}

		button.prop('disabled', true).text('<?php esc_html_e(
      "Migrating...",
      "vira-code",
  ); ?>');

		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'vira_code_migrate_library_to_files',
				nonce: viraNonce
			},
			success: function(response) {
				if (response.success) {
					// Show success message
					const notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
					$('.vira-library-intro').prepend(notice);

					// Reload page after 2 seconds
					setTimeout(function() {
						location.reload();
					}, 2000);
				} else {
					button.prop('disabled', false).text(originalText);
					alert(response.data.message || '<?php esc_html_e(
         "Migration failed.",
         "vira-code",
     ); ?>');
				}
			},
			error: function() {
				button.prop('disabled', false).text(originalText);
				alert('<?php esc_html_e(
        "An error occurred during migration.",
        "vira-code",
    ); ?>');
			}
		});
	});

	// Library stats
	$('#vira-library-stats-btn').on('click', function() {
		$.ajax({
			url: ajaxUrl,
			type: 'POST',
			data: {
				action: 'vira_code_get_library_stats',
				nonce: viraNonce
			},
			success: function(response) {
				if (response.success) {
					const stats = response.data.file_storage;
					let message = '<?php esc_html_e("Library Statistics:", "vira-code"); ?>\n\n';
					message += '<?php esc_html_e(
         "Total Snippets:",
         "vira-code",
     ); ?> ' + stats.total_snippets + '\n';
					message += '<?php esc_html_e(
         "Categories:",
         "vira-code",
     ); ?> ' + stats.categories + '\n';
					message += '<?php esc_html_e(
         "Storage Directory:",
         "vira-code",
     ); ?> ' + stats.woocommerce_directory;

					alert(message);
				} else {
					alert(response.data.message || '<?php esc_html_e(
         "Failed to get library stats.",
         "vira-code",
     ); ?>');
				}
			},
			error: function() {
				alert('<?php esc_html_e(
        "An error occurred while getting stats.",
        "vira-code",
    ); ?>');
			}
		});
	});

	// Existing library functionality...
	// Filter functionality
	$('#vira-filter-category, #vira-filter-search').on('change input', function() {
		filterSnippets();
	});

	function filterSnippets() {
		const categoryFilter = $('#vira-filter-category').val().toLowerCase();
		const searchFilter = $('#vira-filter-search').val().toLowerCase();

		$('.vira-library-card').each(function() {
			const card = $(this);
			const category = card.data('category');
			const tags = card.data('tags');
			const title = card.find('.vira-card-title').text().toLowerCase();
			const description = card.find('.vira-card-description').text().toLowerCase();

			let show = true;

			// Category filter
			if (categoryFilter && category !== categoryFilter) {
				show = false;
			}

			// Search filter
			if (searchFilter &&
				!title.includes(searchFilter) &&
				!description.includes(searchFilter) &&
				!tags.includes(searchFilter)) {
				show = false;
			}

			card.toggle(show);
		});
	}

	// View snippet modal
	$('.vira-view-snippet-btn').on('click', function() {
		const snippet = JSON.parse($(this).attr('data-snippet'));
		showSnippetModal(snippet);
	});

	// Use snippet button
	$('.vira-use-snippet-btn, .vira-modal-use-btn').on('click', function() {
		const snippet = JSON.parse($(this).attr('data-snippet') || $('.vira-modal-use-btn').attr('data-snippet'));
		useSnippet(snippet);
	});

	// Modal functionality
	function showSnippetModal(snippet) {
		$('#vira-modal-title').text(snippet.title || snippet.name);
		$('#vira-modal-description').text(snippet.description);
		$('#vira-modal-type').text(snippet.type.toUpperCase());
		$('#vira-modal-scope').text(snippet.scope);
		$('#vira-modal-category').text(snippet.category);
		$('#vira-modal-code').text(snippet.code);
		$('.vira-modal-use-btn').attr('data-snippet', JSON.stringify(snippet));
		$('#vira-snippet-modal').show();
	}

	// Close modal
	$('.vira-modal-close, .vira-modal-close-btn, .vira-modal-overlay').on('click', function() {
		$('#vira-snippet-modal').hide();
	});

	// Copy code functionality
	$('.vira-copy-code-btn').on('click', function() {
		const code = $('#vira-modal-code').text();
		navigator.clipboard.writeText(code).then(function() {
			const button = $('.vira-copy-code-btn');
			const originalText = button.text();
			button.text('<?php esc_html_e("Copied!", "vira-code"); ?>');
			setTimeout(function() {
				button.text(originalText);
			}, 2000);
		});
	});

	// Use snippet functionality
	function useSnippet(snippet) {
		// Redirect to snippet editor with pre-filled data
		const params = new URLSearchParams({
			title: snippet.title || snippet.name,
			description: snippet.description,
			code: snippet.code,
			type: snippet.type,
			scope: snippet.scope,
			category: snippet.category,
			tags: Array.isArray(snippet.tags) ? snippet.tags.join(', ') : snippet.tags
		});

		window.location.href = '<?php echo esc_url(
      admin_url("admin.php?page=vira-code-new"),
  ); ?>&' + params.toString();
	}
});
</script>
