(function ($) {
  'use strict';
  function handleSubmit(event) {
    event.preventDefault();
    var $form = $(event.currentTarget);
    var $status = $form.find('.impactshop-card-request__status');
    $status.removeClass('is-error is-success').text('Feldolgozás...');

    var data = new FormData($form[0]);
    data.append('action', 'impactshop_card_request');
    data.append('nonce', impactshopCardRequest.nonce);

    $.ajax({
      url: impactshopCardRequest.ajaxUrl,
      method: 'POST',
      data: data,
      processData: false,
      contentType: false,
    })
      .done(function (response) {
        if (response && response.success) {
          $status.addClass('is-success').text(response.data.message || 'Mentve');
          $form[0].reset();
        } else {
          var msg = (response && response.data && response.data.message) ? response.data.message : 'Ismeretlen hiba';
          $status.addClass('is-error').text(msg);
        }
      })
      .fail(function (xhr) {
        var msg = 'Hálózati hiba (' + xhr.status + ')';
        $status.addClass('is-error').text(msg);
      });
  }

  $(function () {
    $('.impactshop-card-request__form').on('submit', handleSubmit);
  });
})(jQuery);
