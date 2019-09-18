/**
 * @file
 * Processes plus-minus clicks.
 */
(function ($) {

  Drupal.behaviors.quantity = {
    attach: function (context) {
      $('.plus-minus-wrapper').each(function() {
        if (!$(this).hasClass('quantity-processed')) {
          $(this).addClass('quantity-processed');
          var latency;
          $('.plus-minus-wrapper .plus, .plus-minus-wrapper .minus', context)
            .once('quantity')
            .click(function() {
              var value = $(this).parent().find('input').val();
              if ($(this).hasClass('plus')) {
                value++;
              } else if (value > 1) {
                value--;
              }
              $(this).parent().find('input').val(value);
              clearTimeout(latency);
              var self = this;
              latency = setTimeout(function() {
                $(self)
                  .parent()
                  .next()
                  .mousedown();
              }, 500);

            });
        }
      });

    }
  };

})(jQuery);