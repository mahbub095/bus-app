export async function ziniPayCreatePayment(payload, apiKey) {
  try {
    // Map payload parameters to match ZiniPay API schema requirements
    const apiPayload = {
      cus_name: payload.cus_name || payload.full_name || "",
      cus_email: payload.cus_email || payload.email || "",
      amount: Number(payload.amount),
      metadata: payload.metadata || {},
      redirect_url: payload.redirect_url,
      cancel_url: payload.cancel_url,
      webhook_url: payload.webhook_url
    };

    const response = await fetch("https://api.zinipay.com/v1/payment/create", {
      method: "POST",
      headers: {
        "zini-api-key": apiKey,
        "Content-Type": "application/json",
        "Accept": "application/json"
      },
      body: JSON.stringify(apiPayload),
    });

    if (!response.ok) {
      const err = await response.text();
      throw new Error(err || "Payment create failed");
    }

    return await response.json();
  } catch (e) {
    console.error("Create Payment Error:", e.message);
    throw e;
  }
}

export async function ziniPayVerifyPayment(invoiceId, transactionId, apiKey) {
  try {
    const payload = { invoice_id: invoiceId };
    if (transactionId) {
      payload.transaction_id = transactionId;
    }
    const response = await fetch("https://api.zinipay.com/v1/payment/verify", {
      method: "POST",
      headers: {
        "zini-api-key": apiKey,
        "Content-Type": "application/json",
        "Accept": "application/json"
      },
      body: JSON.stringify(payload),
    });

    if (!response.ok) {
      const err = await response.text();
      throw new Error(err || "Payment verify failed");
    }

    return await response.json();
  } catch (e) {
    console.error("Verify Payment Error:", e.message);
    throw e;
  }
}

export async function handleZiniPayRedirect({
  apiBase,
  setVerificationStatus,
  setBookingSuccess,
  setPaymentFailed,
  showToast
}) {
  const urlParams = new URLSearchParams(window.location.search);
  const payment = urlParams.get('payment');
  const bookingId = urlParams.get('booking_id');
  const errorMsg = urlParams.get('error');
  const invoiceId = urlParams.get('invoiceId');

  if (invoiceId) {
    setVerificationStatus({
      loading: true,
      invoiceId,
      success: false,
      message: '',
      transactionId: '',
      bookingId: null
    });

    try {
      const apiKey = import.meta.env.VITE_ZINIPAY_API_KEY || "90e76eb23cdf5ec69fe8820a5007b8713844626087a8fb86";
      const response = await ziniPayVerifyPayment(invoiceId, null, apiKey);
      console.log("response from verify payment:", response);

      if (response.status === "COMPLETED") {
        // Call backend webhook to update status in DB securely
        const updateRes = await fetch(`${apiBase}/payment/webhook`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
          },
          body: JSON.stringify({
            invoice_id: invoiceId,
            status: 'COMPLETED'
          })
        });

        if (updateRes.ok) {
          const updateData = await updateRes.json();
          const bId = updateData.booking?.id || null;

          setVerificationStatus({
            loading: false,
            invoiceId,
            success: true,
            message: 'Verified successfully!',
            transactionId: response.transaction_id || response.payment_id || 'N/A',
            bookingId: bId
          });
          showToast('Payment verified successfully!', 'success');
        } else {
          setVerificationStatus({
            loading: false,
            invoiceId,
            success: false,
            message: 'Payment verified, but failed to update local database.',
            transactionId: '',
            bookingId: null
          });
          showToast('Failed to update booking status.', 'error');
        }
      } else {
        setVerificationStatus({
          loading: false,
          invoiceId,
          success: false,
          message: response.message || `Payment status: ${response.status}`,
          transactionId: '',
          bookingId: null
        });
        showToast('Payment verification failed.', 'error');
      }
    } catch (err) {
      console.error("ZiniPay verification failed", err);
      setVerificationStatus({
        loading: false,
        invoiceId,
        success: false,
        message: 'Error communicating with ZiniPay.',
        transactionId: '',
        bookingId: null
      });
      showToast('Verification failed.', 'error');
    }
    window.history.replaceState({}, document.title, window.location.pathname);
  } else if (payment === 'success' && bookingId) {
    fetch(`${apiBase}/bookings/public/${bookingId}`)
      .then(res => {
        if (res.ok) return res.json();
        throw new Error('Failed to load transaction receipt details.');
      })
      .then(data => {
        setBookingSuccess(data);
        showToast('Payment completed successfully via ZiniPay!', 'success');
        window.history.replaceState({}, document.title, window.location.pathname);
      })
      .catch(err => {
        showToast(err.message, 'error');
      });
  } else if (payment === 'cancelled') {
    setPaymentFailed({ type: 'cancelled' });
    window.history.replaceState({}, document.title, window.location.pathname);
  } else if (payment === 'failed') {
    setPaymentFailed({ type: 'failed', errorMsg: errorMsg ? decodeURIComponent(errorMsg) : '' });
    window.history.replaceState({}, document.title, window.location.pathname);
  }
}

