/**
 * Vira Code Admin JavaScript
 *
 * @package ViraCode
 */

(function ($) {
  "use strict";

  // Debug: Log that script is loading
  if (window.console && console.log) {
    console.log("[Vira Code] admin.js is loading...");
  }

  // Vira Code Admin Object
  const ViraCodeAdmin = {
    /**
     * Initialize
     */
    init: function () {
      // Mark as initialized
      this.initialized = true;
      
      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Initializing admin interface...");
        console.log("[Vira Code] jQuery version:", $.fn.jquery);
        console.log(
          "[Vira Code] viraCode object available:",
          typeof viraCode !== "undefined",
        );
        if (typeof viraCode !== "undefined") {
          console.log("[Vira Code] AJAX URL:", viraCode.ajaxUrl);
          console.log("[Vira Code] REST URL:", viraCode.restUrl);
          console.log("[Vira Code] Nonce present:", !!viraCode.nonce);
        }
      }

      // Check if required objects exist
      if (typeof viraCode === "undefined") {
        console.error(
          "[Vira Code] ERROR: viraCode object not found! Scripts may not be localized properly.",
        );
        
        // Don't show alert on snippets list page as it has its own fallback
        if (window.location.href.indexOf('page=vira-code') !== -1 &&
            window.location.href.indexOf('page=vira-code-new') === -1) {
          console.log("[Vira Code] On snippets list page, using fallback initialization");
          // Continue with basic initialization for snippets list
          this.bindEvents();
          return;
        }
        
        // Try to continue with minimal functionality on other pages
        console.log("[Vira Code] Attempting to continue with minimal functionality");
        this.bindEvents();
        return;
      }

      this.bindEvents();
      this.initCodeEditor();

      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Initialization complete!");
      }
    },

    /**
     * Bind event handlers
     */
    bindEvents: function () {
      // Save snippet - add direct button click handler
      $(document).on("click", "#vira-save-snippet", this.saveSnippet.bind(this));

      // Delete snippet
      $(document).on(
        "click",
        ".vira-delete-snippet",
        function(e) {
          e.preventDefault();
          e.stopPropagation();
          ViraCodeAdmin.deleteSnippet.call(ViraCodeAdmin, e);
        },
      );

      // Toggle snippet status
      $(document).on(
        "click",
        ".vira-toggle-snippet",
        function(e) {
          e.preventDefault();
          e.stopPropagation();
          ViraCodeAdmin.toggleSnippet.call(ViraCodeAdmin, e);
        },
      );

      // Test snippet
      $("#vira-test-snippet").on("click", this.testSnippet.bind(this));

      // Prevent accidental navigation
      this.preventAccidentalNavigation();
    },

    /**
     * Initialize code editor (handled by inline script in view)
     */
    initCodeEditor: function () {
      // CodeMirror initialization is handled by inline script in snippet-editor.php
      // This function exists for compatibility but does nothing
      if (window.console && console.log) {
        console.log(
          "[Vira Code] CodeMirror initialization handled by view template",
        );
      }
    },

    /**
     * Save snippet
     */
    saveSnippet: function (e) {
      e.preventDefault();

      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Save snippet button clicked");
      }

      // Check if viraCode object exists
      if (typeof viraCode === "undefined") {
        alert(
          "Error: Vira Code JavaScript not properly initialized. Please refresh the page.",
        );
        console.error("[Vira Code] viraCode object is not defined!");
        return;
      }

      const button = $(e.currentTarget);
      const form = $("#vira-snippet-form");
      const originalText = button.html();

      // Sync CodeMirror content back to textarea (multiple attempts to ensure it's saved)
      if (window.viraCodeEditor && window.viraCodeEditor.codemirror) {
        try {
          // First sync
          window.viraCodeEditor.codemirror.save();
          
          // Small delay and second sync to ensure content is properly saved
          setTimeout(function() {
            window.viraCodeEditor.codemirror.save();
            
            if (window.console && console.log) {
              console.log("[Vira Code] CodeMirror content synced to textarea (double-sync)");
              console.log(
                "[Vira Code] Textarea value length:",
                $("#snippet-code").val().length,
              );
              console.log(
                "[Vira Code] Textarea content preview:",
                $("#snippet-code").val().substring(0, 100) + "..."
              );
            }
          }, 50);
          
        } catch (error) {
          console.error("[Vira Code] Error syncing CodeMirror:", error);
        }
      } else {
        if (window.console && console.log) {
          console.log(
            "[Vira Code] No CodeMirror instance found, using textarea value directly",
          );
        }
      }

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

      // Validate
      if (!formData.title || !formData.code) {
        this.showNotice(
          "error",
          viraCode.i18n.save_error || "Title and code are required.",
        );
        console.warn("[Vira Code] Validation failed: Missing title or code");
        return;
      }

      // Disable button
      button
        .prop("disabled", true)
        .html('<span class="dashicons dashicons-update"></span> Saving...');

      // Wait a moment for the double-sync to complete before sending AJAX
      setTimeout(function() {
        // Re-get form data after sync to ensure we have the latest content
        const updatedFormData = {
          action: "vira_code_save_snippet",
          nonce: viraCode.nonce,
          id: $("#snippet-id").val(),
          title: $("#snippet-title").val(),
          description: $("#snippet-description").val(),
          code: $("#snippet-code").val(), // This should now have the synced content
          type: $("#snippet-type").val(),
          scope: $("#snippet-scope").val(),
          status: $("#snippet-status").val(),
          tags: $("#snippet-tags").val(),
          category: $("#snippet-category").val(),
          priority: $("#snippet-priority").val(),
        };

        // Debug logging
        if (window.console && console.log) {
          console.log("[Vira Code] Sending AJAX request to:", viraCode.ajaxUrl);
          console.log("[Vira Code] Final textarea value length:", updatedFormData.code ? updatedFormData.code.length : 0);
          console.log("[Vira Code] Final form data:", {
            id: updatedFormData.id,
            title: updatedFormData.title,
            type: updatedFormData.type,
            codeLength: updatedFormData.code ? updatedFormData.code.length : 0,
          });
        }

        // Send AJAX request (send the updatedFormData captured after sync)
        $.ajax({
          url: viraCode.ajaxUrl,
          type: "POST",
          data: updatedFormData,
          success: function (response) {
          // Debug logging
          if (window.console && console.log) {
            console.log("[Vira Code] AJAX response:", response);
          }

          if (response.success) {
            ViraCodeAdmin.showNotice("success", response.data.message);

            // Update snippet ID if new
            if (response.data.snippet_id && !updatedFormData.id) {
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
            form.data("saved", true);
          } else {
            ViraCodeAdmin.showNotice(
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
              ViraCodeAdmin.showNotice("error", errorMsg);
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

          ViraCodeAdmin.showNotice(
            "error",
            "An error occurred. Please try again. Check browser console for details.",
          );
          button.prop("disabled", false).html(originalText);
        },
        });
      }, 100); // Wait 100ms for sync to complete
    },

    /**
     * Delete snippet
     */
    deleteSnippet: function (e) {
      e.preventDefault();

      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Delete snippet clicked");
      }

      // Check if viraCode object exists
      if (typeof viraCode === "undefined") {
        console.error("[Vira Code] viraCode object is not defined!");
        // Let the inline script handle this if we're on the snippets list page
        if (window.location.href.indexOf('page=vira-code') !== -1 &&
            window.location.href.indexOf('page=vira-code-new') === -1) {
          console.log("[Vira Code] On snippets list page, letting inline script handle delete");
          return; // Don't prevent the inline script from handling it
        }
        alert(
          "Error: Vira Code JavaScript not properly initialized. Please refresh the page.",
        );
        return;
      }

      if (
        !confirm(
          viraCode.i18n.confirm_delete ||
            "Are you sure you want to delete this snippet?",
        )
      ) {
        return;
      }

      const link = $(e.currentTarget);
      const snippetId = link.data("id");
      const row = link.closest("tr");

      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Deleting snippet ID:", snippetId);
      }

      // Send AJAX request
      $.ajax({
        url: viraCode.ajaxUrl,
        type: "POST",
        data: {
          action: "vira_code_delete_snippet",
          nonce: viraCode.nonce,
          id: snippetId,
        },
        beforeSend: function () {
          row.addClass("vira-loading");
        },
        success: function (response) {
          if (response.success) {
            row.fadeOut(300, function () {
              $(this).remove();
              ViraCodeAdmin.updateStatistics();
              ViraCodeAdmin.showNotice("success", response.data.message);
            });
          } else {
            row.removeClass("vira-loading");
            ViraCodeAdmin.showNotice(
              "error",
              response.data.message || viraCode.i18n.delete_error,
            );
          }
        },
        error: function (xhr, status, error) {
          console.error("[Vira Code] AJAX error:", {
            status: status,
            error: error,
            xhr: xhr,
          });
          row.removeClass("vira-loading");
          ViraCodeAdmin.showNotice(
            "error",
            "An error occurred. Please try again.",
          );
        },
      });
    },

    /**
     * Toggle snippet status
     */
    toggleSnippet: function (e) {
      e.preventDefault();

      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Toggle snippet clicked");
      }

      const link = $(e.currentTarget);
      const snippetId = link.data("id");
      const row = link.closest("tr");

      // Debug logging
      if (window.console && console.log) {
        console.log("[Vira Code] Snippet ID:", snippetId);
        console.log("[Vira Code] viraCode object:", typeof viraCode !== "undefined" ? "available" : "not available");
      }

      // Check if viraCode object exists
      if (typeof viraCode === "undefined") {
        console.error("[Vira Code] viraCode object is not defined!");
        // Let the inline script handle this if we're on the snippets list page
        if (window.location.href.indexOf('page=vira-code') !== -1 &&
            window.location.href.indexOf('page=vira-code-new') === -1) {
          console.log("[Vira Code] On snippets list page, letting inline script handle toggle");
          return; // Don't prevent the inline script from handling it
        }
        alert(
          "Error: Vira Code JavaScript not properly initialized. Please refresh the page.",
        );
        return;
      }

      // Send AJAX request
      $.ajax({
        url: viraCode.ajaxUrl,
        type: "POST",
        data: {
          action: "vira_code_toggle_snippet",
          nonce: viraCode.nonce,
          id: snippetId,
        },
        beforeSend: function () {
          row.addClass("vira-loading");
        },
        success: function (response) {
          // Debug logging
          if (window.console && console.log) {
            console.log("[Vira Code] Toggle response:", response);
          }

          if (response.success) {
            // Update link text
            const newText =
              response.data.status === "active" ? "Deactivate" : "Activate";
            link.text(newText);

            // Update status badge
            const statusCell = row.find(".column-status");
            const statusClass =
              response.data.status === "active" ? "success" : "default";
            const statusText =
              response.data.status.charAt(0).toUpperCase() +
              response.data.status.slice(1);
            statusCell.html(
              '<span class="vira-status vira-status-' +
                statusClass +
                '">' +
                statusText +
                "</span>",
            );

            // Update statistics on the page
            ViraCodeAdmin.updateStatistics();

            ViraCodeAdmin.showNotice("success", response.data.message);
          } else {
            ViraCodeAdmin.showNotice(
              "error",
              response.data.message || "Failed to toggle snippet status.",
            );
          }

          row.removeClass("vira-loading");
        },
        error: function (xhr, status, error) {
          console.error("[Vira Code] AJAX error:", {
            status: status,
            error: error,
            xhr: xhr,
          });
          row.removeClass("vira-loading");
          ViraCodeAdmin.showNotice(
            "error",
            "An error occurred. Please try again.",
          );
        },
      });
    },

    /**
     * Test snippet
     */
    testSnippet: function (e) {
      e.preventDefault();

      const button = $(e.currentTarget);
      const originalText = button.html();
      const snippetId = $("#snippet-id").val();

      // Sync CodeMirror content
      if (window.viraCodeEditor && window.viraCodeEditor.codemirror) {
        window.viraCodeEditor.codemirror.save();
      }

      // Get snippet data
      const snippetType = $("#snippet-type").val();
      const snippetCode = $("#snippet-code").val();

      // Validate required fields
      if (!snippetCode) {
        this.showNotice("error", "Please enter code to test.");
        return;
      }

      // For non-PHP snippets, show info message
      if (snippetType !== "php") {
        this.showNotice(
          "info",
          "Testing is only available for PHP snippets. JavaScript, CSS, and HTML snippets will be outputted when activated.",
        );
        return;
      }

      // Disable button
      button
        .prop("disabled", true)
        .html('<span class="dashicons dashicons-update"></span> Testing...');

      // If snippet is saved, use execute endpoint
      if (snippetId) {
        $.ajax({
          url: viraCode.restUrl + "snippets/" + snippetId + "/execute",
          type: "POST",
          beforeSend: function (xhr) {
            xhr.setRequestHeader("X-WP-Nonce", viraCode.restNonce);
          },
          success: function (response) {
            button.prop("disabled", false).html(originalText);
            ViraCodeAdmin.handleTestResponse(response);
          },
          error: function () {
            button.prop("disabled", false).html(originalText);
            ViraCodeAdmin.showNotice(
              "error",
              "An error occurred while testing the snippet.",
            );
          },
        });
      } else {
        // For new snippets, validate via AJAX
        $.ajax({
          url: viraCode.ajaxUrl,
          type: "POST",
          data: {
            action: "vira_code_validate_snippet",
            nonce: viraCode.nonce,
            code: snippetCode,
            type: snippetType,
          },
          success: function (response) {
            button.prop("disabled", false).html(originalText);

            if (response.success) {
              ViraCodeAdmin.showNotice(
                "success",
                "<strong>Validation passed!</strong><br>Your PHP code has no syntax errors. You can safely save and activate this snippet.",
              );
            } else {
              let message = "<strong>Validation failed!</strong>";
              if (response.data && response.data.error) {
                message +=
                  '<br><br><strong>Error:</strong><br><pre style="max-height: 200px; overflow: auto; padding: 10px; background: #f8d7da; border-radius: 3px; color: #721c24;">' +
                  ViraCodeAdmin.escapeHtml(response.data.error) +
                  "</pre>";
              }
              ViraCodeAdmin.showNotice("error", message);
            }
          },
          error: function () {
            button.prop("disabled", false).html(originalText);
            ViraCodeAdmin.showNotice(
              "error",
              "An error occurred while validating the snippet.",
            );
          },
        });
      }
    },

    /**
     * Handle test response
     */
    handleTestResponse: function (response) {
      if (response.success) {
        let message = "<strong>Snippet executed successfully!</strong>";
        if (response.output) {
          message +=
            '<br><br><strong>Output:</strong><br><pre style="max-height: 200px; overflow: auto; padding: 10px; background: #f6f7f7; border-radius: 3px;">' +
            ViraCodeAdmin.escapeHtml(response.output) +
            "</pre>";
        }
        ViraCodeAdmin.showNotice("success", message);
      } else {
        let message = "<strong>Snippet execution failed!</strong>";
        if (response.error) {
          message +=
            '<br><br><strong>Error:</strong><br><pre style="max-height: 200px; overflow: auto; padding: 10px; background: #f8d7da; border-radius: 3px; color: #721c24;">' +
            ViraCodeAdmin.escapeHtml(response.error) +
            "</pre>";
        }
        ViraCodeAdmin.showNotice("error", message);
      }
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

      // Reset on save - use a different approach since submit is handled elsewhere
      $(document).on("click", "#vira-save-snippet", function () {
        formChanged = false;
      });

      // Warn on navigation
      $(window).on("beforeunload", function () {
        if (formChanged && !$("#vira-snippet-form").data("saved")) {
          return "You have unsaved changes. Are you sure you want to leave?";
        }
      });
    },
    /**
     * Update statistics on the page
     */
    updateStatistics: function () {
      // Check if viraCode object exists
      if (typeof viraCode === "undefined") {
        console.error("[Vira Code] viraCode object is not defined, cannot update statistics");
        return;
      }
      
      // Send AJAX request to get updated statistics
      $.ajax({
        url: viraCode.ajaxUrl,
        type: "POST",
        data: {
          action: "vira_code_get_statistics",
          nonce: viraCode.nonce,
        },
        success: function (response) {
          if (response.success && response.data.stats) {
            // Update statistics cards
            $(".vira-stat-value").each(function (index) {
              const statKey = ["total", "active", "inactive", "error"][index];
              if (response.data.stats[statKey] !== undefined) {
                $(this).text(response.data.stats[statKey]);
              }
            });
          }
        },
      });
    },

    /**
     * Escape HTML
     */
    escapeHtml: function (text) {
      const map = {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#039;",
      };
      return text.replace(/[&<>"']/g, function (m) {
        return map[m];
      });
    },
  };

  // Make ViraCodeAdmin available globally
  window.ViraCodeAdmin = ViraCodeAdmin;

  // Initialize on document ready
  $(document).ready(function () {
    if (window.console && console.log) {
      console.log("[Vira Code] Document ready, initializing...");
    }

    // Verify jQuery is available
    if (typeof jQuery === "undefined") {
      console.error("[Vira Code] ERROR: jQuery is not loaded!");
      return;
    }

    // Create viraCode object if it doesn't exist (for snippets list page)
    if (typeof window.viraCode === "undefined") {
      window.viraCode = {
        ajaxUrl: ajaxurl || '/wp-admin/admin-ajax.php',
        nonce: '',
        i18n: {
          confirm_delete: "Are you sure you want to delete this snippet?",
          delete_error: "Error deleting snippet. Please try again."
        }
      };
      console.log("[Vira Code] Created fallback viraCode object in admin.js");
    }

    // Wait a bit for scripts to be localized
    setTimeout(function() {
      ViraCodeAdmin.init();
    }, 100);
  });
  
  // Also initialize on window load as a fallback
  $(window).on('load', function() {
    if (typeof ViraCodeAdmin !== 'undefined' && !ViraCodeAdmin.initialized) {
      ViraCodeAdmin.init();
    }
  });

  // Debug: Log that script has loaded
  if (window.console && console.log) {
    console.log("[Vira Code] admin.js loaded successfully");
  }
})(jQuery);
