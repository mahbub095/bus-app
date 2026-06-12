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
