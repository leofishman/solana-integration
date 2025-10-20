/**
 * @file
 * Solana Pay checkout behaviors.
 */

(function (Drupal, drupalSettings) {
  'use strict';

  Drupal.behaviors.solanaPayCheckout = {
    attach: function (context, settings) {
      const config = settings.solanaPay;
      if (!config) {
        return;
      }

      const qrContainer = document.getElementById('solana-pay-qr');
      const statusMessage = document.getElementById('solana-pay-status');
      const walletLink = document.getElementById('solana-pay-open');

      if (!qrContainer || !statusMessage) {
        return;
      }

      if (typeof QRCode === 'undefined') {
        console.error('QRCode library not loaded');
        statusMessage.textContent = 'Error: QR code library not available.';
        return;
      }

      qrContainer.innerHTML = '';
      new QRCode(qrContainer, {
        text: config.solanaUrl,
        width: 256,
        height: 256,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.M
      });

      if (walletLink) {
        walletLink.href = config.solanaUrl;
      }

      let pollCount = 0;
      const maxPolls = 40;
      const pollInterval = 3000;

      const pollStatus = function() {
        if (pollCount >= maxPolls) {
          statusMessage.textContent = Drupal.t('Payment verification timeout. Please refresh the page or contact support if you completed the payment.');
          statusMessage.className = 'solana-pay-status error';
          return;
        }

        pollCount++;

        fetch(config.statusUrl, {
          method: 'GET',
          credentials: 'same-origin',
          headers: {
            'Accept': 'application/json'
          }
        })
        .then(response => response.json())
        .then(data => {
          if (data.status === 'confirmed') {
            statusMessage.textContent = Drupal.t('Payment confirmed! Redirecting...');
            statusMessage.className = 'solana-pay-status success';
            setTimeout(function() {
              window.location.href = config.returnUrl;
            }, 1000);
          }
          else if (data.status === 'pending') {
            statusMessage.textContent = Drupal.t('Waiting for payment confirmation... (@count/@max)', {
              '@count': pollCount,
              '@max': maxPolls
            });
            statusMessage.className = 'solana-pay-status pending';
            setTimeout(pollStatus, pollInterval);
          }
          else {
            statusMessage.textContent = Drupal.t('Payment status check failed. Please try again.');
            statusMessage.className = 'solana-pay-status error';
          }
        })
        .catch(error => {
          console.error('Payment status check error:', error);
          if (pollCount < maxPolls) {
            setTimeout(pollStatus, pollInterval);
          }
        });
      };

      setTimeout(pollStatus, pollInterval);

      const manualCheckButton = document.createElement('button');
      manualCheckButton.textContent = Drupal.t("I've paid - Check now");
      manualCheckButton.className = 'button solana-pay-manual-check';
      manualCheckButton.onclick = function(e) {
        e.preventDefault();
        pollCount = 0;
        pollStatus();
      };
      statusMessage.parentNode.insertBefore(manualCheckButton, statusMessage.nextSibling);
    }
  };

})(Drupal, drupalSettings);
