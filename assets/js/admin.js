/**
 * Rank Math API Manager - Admin JavaScript
 *
 * @since 1.0.7
 */

jQuery(document).ready(function ($) {
  // Update check functionality
  $("#rank-math-api-check-updates").on("click", function (e) {
    e.preventDefault();

    var $button = $(this);
    var originalText = $button.text();

    $button.prop("disabled", true).text(rankMathApi.strings.checkingUpdates);

    $.ajax({
      url: rankMathApi.ajaxUrl,
      type: "POST",
      data: {
        action: "rank_math_api_check_updates",
        nonce: rankMathApi.nonce,
      },
      success: function (response) {
        if (response.success) {
          if (response.data.update_available) {
            alert(
              rankMathApi.strings.updateAvailable +
                "\n\nVersion: " +
                response.data.latest_version +
                "\n\nPlease refresh the page to see the update notification."
            );
            location.reload();
          } else {
            alert(rankMathApi.strings.noUpdateAvailable);
          }
        } else {
          alert(response.data.message || rankMathApi.strings.errorChecking);
        }
      },
      error: function () {
        alert(rankMathApi.strings.errorChecking);
      },
      complete: function () {
        $button.prop("disabled", false).text(originalText);
      },
    });
  });

  // Force update functionality
  $("#rank-math-api-force-update").on("click", function (e) {
    e.preventDefault();

    if (
      !confirm(
        "Are you sure you want to force check for updates? This will bypass the cache."
      )
    ) {
      return;
    }

    var $button = $(this);
    var originalText = $button.text();

    $button.prop("disabled", true).text("Checking...");

    $.ajax({
      url: rankMathApi.ajaxUrl,
      type: "POST",
      data: {
        action: "rank_math_api_force_update_check",
        nonce: rankMathApi.nonce,
      },
      success: function (response) {
        if (response.success) {
          if (response.data.update_available) {
            alert(
              "Update available!\n\nVersion: " +
                response.data.latest_version +
                "\n\nPlease refresh the page to see the update notification."
            );
            location.reload();
          } else {
            alert("No updates available.");
          }
        } else {
          alert(response.data.message || "Error checking for updates.");
        }
      },
      error: function () {
        alert("Error checking for updates.");
      },
      complete: function () {
        $button.prop("disabled", false).text(originalText);
      },
    });
  });
});
