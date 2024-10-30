jQuery(function () {
  jQuery(document).on('click', '.notice[data-notice="custom_admin_notice"] .notice-dismiss', function () {
    jQuery.ajax({
      url: customAdminNotice.ajax_url,
      type: 'POST',
      data: {
        action: 'dismiss_custom_admin_notice',
        nonce: customAdminNotice.nonce
      }
    });
  });
});