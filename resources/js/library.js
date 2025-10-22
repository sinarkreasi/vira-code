/**
 * Vira Code Library JavaScript
 *
 * @package ViraCode
 */

(function ($) {
  "use strict";

  // Vira Code Library Object
  const ViraCodeLibrary = {
    currentSnippet: null,

    /**
     * Initialize
     */
    init: function () {
      this.bindEvents();
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      // Filter by category
      $("#vira-filter-category").on("change", this.onCategoryChange.bind(this));

      // Filter by search
      $("#vira-filter-search").on("keyup", this.onSearchKeyup.bind(this));

      // View snippet code
      $(document).on(
        "click",
        ".vira-view-snippet-btn",
        this.onViewSnippet.bind(this),
      );

      // Use snippet directly
      $(document).on(
        "click",
        ".vira-use-snippet-btn",
        this.onUseSnippet.bind(this),
      );

      // Use snippet from modal
      $(document).on(
        "click",
        ".vira-modal-use-btn",
        this.onModalUseSnippet.bind(this),
      );

      // Copy code to clipboard
      $(document).on(
        "click",
        ".vira-copy-code-btn",
        this.onCopyCode.bind(this),
      );

      // Close modal
      $(document).on(
        "click",
        ".vira-modal-close, .vira-modal-close-btn, .vira-modal-overlay",
        this.onCloseModal.bind(this),
      );

      // Close modal with ESC key
      $(document).on("keyup", this.onEscapeKey.bind(this));
    },

    /**
     * Handle category filter change
     */
    onCategoryChange: function (e) {
      this.filterSnippets();
    },

    /**
     * Handle search input keyup
     */
    onSearchKeyup: function (e) {
      this.filterSnippets();
    },

    /**
     * Filter snippets based on category and search
     */
    filterSnippets: function () {
      const category = $("#vira-filter-category").val().toLowerCase();
      const search = $("#vira-filter-search").val().toLowerCase();

      $(".vira-library-card").each(function () {
        const $card = $(this);
        const cardCategory = $card.data("category");
        const cardTags = $card.data("tags");
        const cardTitle = $card.find(".vira-card-title").text().toLowerCase();
        const cardDesc = $card
          .find(".vira-card-description")
          .text()
          .toLowerCase();

        const categoryMatch = !category || cardCategory === category;
        const searchMatch =
          !search ||
          cardTitle.indexOf(search) > -1 ||
          cardDesc.indexOf(search) > -1 ||
          cardTags.indexOf(search) > -1;

        if (categoryMatch && searchMatch) {
          $card.show();
        } else {
          $card.hide();
        }
      });
    },

    /**
     * Handle view snippet button click
     */
    onViewSnippet: function (e) {
      e.preventDefault();
      const snippet = $(e.currentTarget).data("snippet");
      this.showSnippetModal(snippet);
    },

    /**
     * Handle use snippet button click
     */
    onUseSnippet: function (e) {
      e.preventDefault();
      const snippet = $(e.currentTarget).data("snippet");
      this.useSnippet(snippet);
    },

    /**
     * Handle modal use button click
     */
    onModalUseSnippet: function (e) {
      e.preventDefault();
      if (this.currentSnippet) {
        this.useSnippet(this.currentSnippet);
      }
    },

    /**
     * Handle copy code button click
     */
    onCopyCode: function (e) {
      e.preventDefault();
      const code = $("#vira-modal-code").text();

      // Create temporary textarea
      const $temp = $("<textarea>");
      $("body").append($temp);
      $temp.val(code).select();
      document.execCommand("copy");
      $temp.remove();

      // Show feedback
      const $btn = $(e.currentTarget);
      const originalText = $btn.html();
      $btn.html(
        '<span class="dashicons dashicons-yes"></span> ' +
          viraCodeLibrary.i18n.copied,
      );

      setTimeout(function () {
        $btn.html(originalText);
      }, 2000);
    },

    /**
     * Handle modal close
     */
    onCloseModal: function (e) {
      e.preventDefault();
      $("#vira-snippet-modal").fadeOut(300);
      this.currentSnippet = null;
    },

    /**
     * Handle ESC key press
     */
    onEscapeKey: function (e) {
      if (e.key === "Escape" && $("#vira-snippet-modal").is(":visible")) {
        $("#vira-snippet-modal").fadeOut(300);
        this.currentSnippet = null;
      }
    },

    /**
     * Show snippet modal
     */
    showSnippetModal: function (snippet) {
      this.currentSnippet = snippet;

      $("#vira-modal-title").text(snippet.title);
      $("#vira-modal-description").text(snippet.description);
      $("#vira-modal-type").text(snippet.type.toUpperCase());
      $("#vira-modal-scope").text(this.capitalizeFirst(snippet.scope));
      $("#vira-modal-category").text(snippet.category);
      $("#vira-modal-code").text(snippet.code);

      $("#vira-snippet-modal").fadeIn(300);
    },

    /**
     * Use snippet - redirect to editor with pre-filled data
     */
    useSnippet: function (snippet) {
      // Create URL with snippet data
      let url = viraCodeLibrary.editorUrl;
      url += "&library=1";
      url += "&title=" + encodeURIComponent(snippet.title);
      url += "&description=" + encodeURIComponent(snippet.description);
      url += "&code=" + encodeURIComponent(snippet.code);
      url += "&type=" + encodeURIComponent(snippet.type);
      url += "&scope=" + encodeURIComponent(snippet.scope);
      url += "&category=" + encodeURIComponent(snippet.category);
      url += "&tags=" + encodeURIComponent(snippet.tags);

      // Redirect to editor
      window.location.href = url;
    },

    /**
     * Capitalize first letter
     */
    capitalizeFirst: function (str) {
      return str.charAt(0).toUpperCase() + str.slice(1);
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    ViraCodeLibrary.init();
  });
})(jQuery);
