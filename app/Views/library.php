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
				<div class="vira-library-card" data-category="<?php echo esc_attr(
        strtolower($snippet["category"]),
    ); ?>" data-tags="<?php echo esc_attr(strtolower($snippet["tags"])); ?>">
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
						<h3 class="vira-card-title"><?php echo esc_html($snippet["title"]); ?></h3>
						<p class="vira-card-description"><?php echo esc_html(
          $snippet["description"],
      ); ?></p>

						<?php if (!empty($snippet["tags"])): ?>
							<div class="vira-card-tags">
								<?php
        $tags = explode(",", $snippet["tags"]);
        foreach ($tags as $tag):
            $tag = trim($tag); ?>
									<span class="vira-tag"><?php echo esc_html($tag); ?></span>
								<?php
        endforeach;
        ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="vira-card-footer">
						<button type="button"
							class="button button-secondary vira-view-snippet-btn"
							data-snippet-id="<?php echo esc_attr($snippet["id"]); ?>"
							data-snippet='<?php echo esc_attr(wp_json_encode($snippet)); ?>'>
							<span class="dashicons dashicons-visibility"></span>
							<?php esc_html_e("View Code", "vira-code"); ?>
						</button>
						<button type="button"
							class="button button-primary vira-use-snippet-btn"
							data-snippet-id="<?php echo esc_attr($snippet["id"]); ?>"
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
