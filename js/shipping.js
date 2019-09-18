/**
 * @file
 * Processes plus-minus clicks.
 */
(function ($) {
  var clicker = function() {
    var defaultSelector = 'input[data-drupal-selector="edit-wbx-shipping-information-recalculate-shipping"]';
    if ($(defaultSelector).length) {
      $(defaultSelector).mousedown();
    } else {
      $('input[data-drupal-selector="edit-rows-col2-shipping-recalculate-shipping"]').mousedown();
    }

  };
  $(document).ready(clicker);

  Drupal.behaviors.shipping = {
    attach: function (context) {
      $('.form-type-radio input[id*="shipping-method"]').change(function() {
        clicker();
      });
      $('.form-type-radio input[id*="edit-person-type"]').change(function() {
        clicker();
      });
    }
  };

})(jQuery);