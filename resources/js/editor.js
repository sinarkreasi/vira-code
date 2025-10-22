/**
 * Vira Code Editor JavaScript
 * Handles CodeMirror initialization and form submission for snippet editor
 *
 * @package ViraCode
 */

(function ($) {
  "use strict";

  // Debug: Log that script is loading
  if (window.console && console.log) {
    console.log("[Vira Code] editor.js is loading...");
  }

  // Vira Code Editor Object
  const ViraCodeEditor = {
    /**
     * Initialize
     */
    init: function () {
      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Initializing editor interface...");
        console.log("[Vira Code] jQuery version:", $.fn.jquery);
        console.log(
          "[Vira Code] viraCode object available:",
          typeof viraCode !== "undefined",
        );
      }

      // Check if required objects exist
      if (typeof viraCode === "undefined") {
        console.error(
          "[Vira Code] ERROR: viraCode object not found! Scripts may not be localized properly.",
        );
        return;
      }

      this.bindEvents();

      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Editor initialization complete!");
      }
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      // Handle save button click only
      $("#vira-save-snippet").on("click", this.handleSaveClick.bind(this));

      // Prevent default form submission - handle via AJAX only
      $("#vira-snippet-form").on("submit", function (e) {
        e.preventDefault();
        console.log("[Vira Code] Form submission prevented - using AJAX");
        return false;
      });

      // Prevent accidental navigation
      this.preventAccidentalNavigation();
    },

    /**
     * Ensure CodeMirror content is synced
     */
    ensureContentSync: function () {
      // Simple sync method - only called when needed
      if (window.viraCodeEditor && window.viraCodeEditor.codemirror) {
        try {
          window.viraCodeEditor.codemirror.save();
        } catch (e) {
          console.warn("[Vira Code] Content sync failed:", e);
        }
      }
    },

    /**
     * Handle form submission - not used anymore
     */
    handleFormSubmit: function (e) {
      e.preventDefault();
      console.log("[Vira Code] Form submission blocked - use AJAX only");
      return false;
    },

    /**
     * Handle save button click
     */
    handleSaveClick: function (e) {
      e.preventDefault();

      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Save button clicked");
      }

      // Sync content and save immediately
      this.ensureContentSync();
      this.saveSnippet();
    },

    /**
     * Handle test button click
     */
    handleTestClick: function (e) {
      e.preventDefault();

      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Test button clicked");
      }

      // Sync CodeMirror content
      this.ensureContentSync();

      // Test snippet functionality is handled by inline script
      console.log("[Vira Code] Test button click handled by inline script");
    },

    /**
     * Sync CodeMirror content to textarea - deprecated, use ensureContentSync
     */
    syncCodeMirrorContent: function () {
      this.ensureContentSync();
    },

    /**
     * Save snippet via AJAX
     */
    saveSnippet: function () {
      // Check if viraCode object exists
      if (typeof viraCode === "undefined") {
        alert(
          "Error: Vira Code JavaScript not properly initialized. Please refresh the page.",
        );
        console.error("[Vira Code] viraCode object is not defined!");
        return;
      }

      const button = $("#vira-save-snippet");
      const originalText = button.html();

      // Get form data
      const formData = {
        action: "vira_code_save_snippet",
        nonce: viraCode.nonce,
        id: $("#snippet-id").val(),
        title: $("#snippet-title").val(),
        description: $("#snippet-description").val(),
        code: $("#snippet-code").val(),
        type: $("#snippet-type").val(),
        scope: $("#snippet-scope").val(),
        status: $("#snippet-status").val(),
        tags: $("#snippet-tags").val(),
        category: $("#snippet-category").val(),
        priority: $("#snippet-priority").val(),
      };

      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Form data:", {
          id: formData.id,
          title: formData.title,
          type: formData.type,
          codeLength: formData.code ? formData.code.length : 0,
        });
      }

      // Enhanced validation
      if (!formData.title || !formData.title.trim()) {
        this.showNotice("error", "Please enter a snippet title.");
        console.warn("[Vira Code] Validation failed: Missing title");
        $("#snippet-title").focus();
        return;
      }

      if (!formData.code || !formData.code.trim()) {
        this.showNotice("error", "Please enter code for your snippet.");
        console.warn("[Vira Code] Validation failed: Missing code");
        // Focus on CodeMirror if available, otherwise textarea
        if (window.viraCodeEditor && window.viraCodeEditor.codemirror) {
          window.viraCodeEditor.codemirror.focus();
        } else {
          $("#snippet-code").focus();
        }
        return;
      }

      // Disable button
      button
        .prop("disabled", true)
        .html('<span class="dashicons dashicons-update"></span> Saving...');

      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Sending AJAX request to:", viraCode.ajaxUrl);
      }

      // Send AJAX request
      $.ajax({
        url: viraCode.ajaxUrl,
        type: "POST",
        data: formData,
        success: function (response) {
          // Debug logging
          if (window.console && console.log) {
            console.log("[Vira Code] AJAX response:", response);
          }

          if (response.success) {
            ViraCodeEditor.showNotice("success", response.data.message);

            // Update snippet ID if new
            if (response.data.snippet_id && !formData.id) {
              $("#snippet-id").val(response.data.snippet_id);

              // Update URL
              const newUrl =
                window.location.href + "&id=" + response.data.snippet_id;
              window.history.replaceState({}, "", newUrl);

              if (window.console && console.log) {
                console.log(
                  "[Vira Code] New snippet created with ID:",
                  response.data.snippet_id,
                );
              }

              // Reload after 1 second
              setTimeout(function () {
                window.location.reload();
              }, 1000);
            }

            // Mark form as saved
            $("#vira-snippet-form").data("saved", true);
          } else {
            ViraCodeEditor.showNotice(
              "error",
              response.data.message || viraCode.i18n.save_error,
            );

            // Show validation errors if any
            if (response.data.errors && response.data.errors.length) {
              let errorMsg = "<ul>";
              response.data.errors.forEach(function (error) {
                errorMsg += "<li>" + error + "</li>";
              });
              errorMsg += "</ul>";
              ViraCodeEditor.showNotice("error", errorMsg);
            }

            console.error("[Vira Code] Save failed:", response.data.message);
          }

          button.prop("disabled", false).html(originalText);
        },
        error: function (xhr, status, error) {
          console.error("[Vira Code] AJAX error:", {
            status: status,
            error: error,
            xhr: xhr,
          });

          ViraCodeEditor.showNotice(
            "error",
            "An error occurred. Please try again. Check browser console for details.",
          );
          button.prop("disabled", false).html(originalText);
        },
      });
    },

    /**
     * Show admin notice
     */
    showNotice: function (type, message) {
      let noticeClass = "notice-error";
      if (type === "success") {
        noticeClass = "notice-success";
      } else if (type === "info") {
        noticeClass = "notice-info";
      }

      const notice = $(
        '<div class="notice ' +
          noticeClass +
          ' is-dismissible"><p>' +
          message +
          "</p></div>",
      );

      // Remove existing notices
      $(".vira-code-admin > .notice").remove();

      // Add new notice
      $(".vira-code-admin").prepend(notice);

      // Initialize dismiss button
      $(document).trigger("wp-updates-notice-added");

      // Auto-dismiss after 5 seconds for success and info messages
      if (type === "success" || type === "info") {
        setTimeout(function () {
          notice.fadeOut(300, function () {
            $(this).remove();
          });
        }, 5000);
      }

      // Scroll to top
      $("html, body").animate({ scrollTop: 0 }, 300);
    },

    /**
     * Prevent accidental navigation
     */
    preventAccidentalNavigation: function () {
      let formChanged = false;

      // Track form changes
      $("#vira-snippet-form :input").on("change input", function () {
        formChanged = true;
      });

      // Track CodeMirror changes
      if (window.viraCodeEditor && window.viraCodeEditor.codemirror) {
        window.viraCodeEditor.codemirror.on("change", function () {
          formChanged = true;
        });
      }

      // Reset on save
      $("#vira-snippet-form").on("submit", function () {
        formChanged = false;
      });

      // Warn on navigation
      $(window).on("beforeunload", function () {
        if (formChanged && !$("#vira-snippet-form").data("saved")) {
          return "You have unsaved changes. Are you sure you want to leave?";
        }
      });
    },
  };

  // Initialize on document ready
  $(document).ready(function () {
    if (window.console && console.log) {
      console.log("[Vira Code] Document ready, initializing editor...");
    }

    // Verify jQuery is available
    if (typeof jQuery === "undefined") {
      console.error("[Vira Code] ERROR: jQuery is not loaded!");
      return;
    }

    // Only initialize if we're on the snippet editor page
    if ($("#snippet-code").length > 0) {
      ViraCodeEditor.init();
    }
  });

  // Make ViraCodeEditor available globally
  window.ViraCodeEditor = ViraCodeEditor;

  // Debug: Log that script has loaded
  if (window.console && console.log) {
    console.log("[Vira Code] editor.js loaded successfully");
  }
})(jQuery);
