/**
 * Avalanche CRM Subscriptions Component
 *
 * Handles modal interactions, payment method selection,
 * plan subscription, plan changes, cancellation,
 * and payment provider redirect flows (Stripe, GoCardless, PayPal).
 */
+(function ($) {
    'use strict';

    // ---------- Modal Helpers ----------

    function openModal(id) {
        var $modal = $('#' + id);
        $modal.css('display', 'flex').hide().fadeIn(200);
        $('body').css('overflow', 'hidden');
    }

    function closeModal(id) {
        var $modal = $('#' + id);
        $modal.fadeOut(200, function () {
            $modal.css('display', 'none');
        });
        $('body').css('overflow', '');
    }

    function closeAllModals() {
        $('.subscriptions-modal').each(function () {
            $(this).fadeOut(200).css('display', 'none');
        });
        $('body').css('overflow', '');
    }

    /**
     * If the AJAX response contains a stripeRedirectUrl, redirect to Stripe.
     * Returns true if redirecting, false otherwise.
     */
    function handleStripeRedirect(data) {
        if (data && data.stripeRedirectUrl) {
            window.location.href = data.stripeRedirectUrl;
            return true;
        }
        return false;
    }

    /**
     * If the AJAX response contains a gcRedirectUrl, redirect to GoCardless.
     * Returns true if redirecting, false otherwise.
     */
    function handleGoCardlessRedirect(data) {
        if (data && data.gcRedirectUrl) {
            window.location.href = data.gcRedirectUrl;
            return true;
        }
        return false;
    }

    /**
     * If the AJAX response contains a paypalRedirectUrl, redirect to PayPal.
     * Returns true if redirecting, false otherwise.
     */
    function handlePayPalRedirect(data) {
        if (data && data.paypalRedirectUrl) {
            window.location.href = data.paypalRedirectUrl;
            return true;
        }
        return false;
    }

    /**
     * Handle any payment provider redirect from an AJAX response.
     * Returns true if redirecting, false otherwise.
     */
    function handlePaymentRedirect(data) {
        return handleStripeRedirect(data) || handleGoCardlessRedirect(data) || handlePayPalRedirect(data);
    }

    // Close modal on overlay click or close button
    $(document).on('click', '.subscriptions-modal__overlay, .subscriptions-modal__close, .subscriptions-modal__close-btn', function (e) {
        e.preventDefault();
        var $modal = $(this).closest('.subscriptions-modal');
        if ($modal.length) {
            closeModal($modal.attr('id'));
        }
    });

    // Close modals on Escape key
    $(document).on('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });

    // ---------- Subscribe to Plan ----------

    $(document).on('click', '.btn-subscribe', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var planId = $btn.data('plan-id');
        var planName = $btn.data('plan-name');
        var planPrice = $btn.data('plan-price');
        var planCycle = $btn.data('plan-cycle');

        $('#subscribe-plan-id').val(planId);
        $('#subscribe-plan-name').text(planName);
        $('#subscribe-plan-price-display').text(planPrice + ' / ' + planCycle);

        // Reset payment selection
        $('#modal-subscribe input[name="payment_method"]').prop('checked', false);

        openModal('modal-subscribe');
    });

    // Intercept the subscribe form submission to handle payment redirects
    $(document).on('ajaxSuccess', '#modal-subscribe form', function (e, context, data) {
        if (handlePaymentRedirect(data)) {
            // Keep the modal open with a "Redirectingâ€¦" message
            var $btn = $(this).find('button[type="submit"]');
            var provider = data.stripeRedirectUrl ? 'Stripe' : (data.gcRedirectUrl ? 'GoCardless' : 'PayPal');
            $btn.text('Redirecting to ' + provider + '...').prop('disabled', true);
            return;
        }
        // Non-redirect: close modal as normal
        closeAllModals();
    });

    // ---------- Resume Payment (Pending Subscription) ----------

    $(document).on('click', '.btn-resume-payment', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var subscriptionId = $btn.data('subscription-id');

        $btn.data('original-text', $btn.text());
        $btn.text('Processing...').prop('disabled', true);

        $.request('onResumePayment', {
            data: { subscription_id: subscriptionId },
            success: function (data) {
                if (handlePaymentRedirect(data)) {
                    $btn.text('Redirecting...');
                    return;
                }
                // Fallback: reload the page
                window.location.reload();
            },
            error: function () {
                var originalText = $btn.data('original-text');
                if (originalText) {
                    $btn.text(originalText).prop('disabled', false);
                }
            }
        });
    });

    // ---------- Cancel Subscription ----------

    $(document).on('click', '.btn-cancel-subscription', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var subscriptionId = $btn.data('subscription-id');
        var planName = $btn.data('plan-name');

        $('#cancel-subscription-id').val(subscriptionId);
        $('#cancel-plan-name').text(planName);

        openModal('modal-cancel-subscription');
    });

    // ---------- Change Payment Method ----------

    $(document).on('click', '.btn-change-payment', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var subscriptionId = $btn.data('subscription-id');
        var currentMethod = $btn.data('current-method');

        $('#change-payment-subscription-id').val(subscriptionId);

        // Pre-select current method
        $('#modal-change-payment input[name="payment_method"]').prop('checked', false);
        $('#modal-change-payment input[name="payment_method"][value="' + currentMethod + '"]').prop('checked', true);

        openModal('modal-change-payment');
    });

    // Handle payment redirect when updating payment method
    $(document).on('ajaxSuccess', '#modal-change-payment form', function (e, context, data) {
        if (handlePaymentRedirect(data)) {
            var $btn = $(this).find('button[type="submit"]');
            var provider = data.stripeRedirectUrl ? 'Stripe' : (data.gcRedirectUrl ? 'GoCardless' : 'PayPal');
            $btn.text('Redirecting to ' + provider + '...').prop('disabled', true);
            return;
        }
        closeAllModals();
    });

    // ---------- Change Plan ----------

    $(document).on('click', '.btn-change-plan', function (e) {
        e.preventDefault();
        var $btn = $(this);
        var subscriptionId = $btn.data('subscription-id');
        var currentPlanId = $btn.data('current-plan-id');

        $('#change-plan-subscription-id').val(subscriptionId);

        // Reset and hide current plan option
        $('#modal-change-plan input[name="plan_id"]').prop('checked', false);
        $('.subscriptions-plan-option').show();
        $('.subscriptions-plan-option[data-plan-id="' + currentPlanId + '"]').hide();

        openModal('modal-change-plan');
    });

    // ---------- Stripe Customer Portal ----------

    $(document).on('click', '.btn-stripe-portal', function (e) {
        e.preventDefault();
        var $btn = $(this);
        $btn.data('original-text', $btn.text());
        $btn.text('Opening...').prop('disabled', true);

        $.request('onOpenStripePortal', {
            success: function (data) {
                if (data && data.stripeRedirectUrl) {
                    $btn.text('Redirecting...');
                    window.location.href = data.stripeRedirectUrl;
                } else {
                    $btn.text($btn.data('original-text')).prop('disabled', false);
                }
            },
            error: function () {
                $btn.text($btn.data('original-text')).prop('disabled', false);
            }
        });
    });

    // ---------- AJAX Complete: Close Modals (default handler for non-Stripe) ----------

    $(document).on('ajaxSuccess', '#modal-cancel-subscription form, #modal-change-plan form', function () {
        closeAllModals();
    });

    // ---------- Loading State for Submit Buttons ----------

    $(document).on('ajaxPromise', '.subscriptions-modal form', function () {
        var $btn = $(this).find('button[type="submit"]');
        $btn.data('original-text', $btn.text());
        $btn.text('Processing...').prop('disabled', true);
    });

    $(document).on('ajaxFail', '.subscriptions-modal form', function () {
        var $btn = $(this).find('button[type="submit"]');
        var originalText = $btn.data('original-text');
        if (originalText) {
            $btn.text(originalText).prop('disabled', false);
        }
    });

    // Reset button on non-redirect ajax complete
    $(document).on('ajaxComplete', '.subscriptions-modal form', function () {
        var $btn = $(this).find('button[type="submit"]');
        // Only reset if not in a "redirecting" state
        if ($btn.text().indexOf('Redirect') === -1) {
            var originalText = $btn.data('original-text');
            if (originalText) {
                $btn.text(originalText).prop('disabled', false);
            }
        }
    });

    // ---------- Clean URL on page load (remove payment provider query params) ----------

    $(document).ready(function () {
        if (window.location.search.match(/stripe_success|stripe_cancel|session_id|gc_success|gc_cancel|redirect_flow_id|paypal_success|paypal_cancel|subscription_id/)) {
            var cleanUrl = window.location.protocol + '//' + window.location.host + window.location.pathname;
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title, cleanUrl);
            }
        }
    });

})(jQuery);
