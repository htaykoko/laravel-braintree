<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Document</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.0/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js">
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.0/css/bootstrap.min.css">

    <style>
        /* Uses Bootstrap stylesheets for styling, see linked CSS*/
        body {
            background-color: #fff;
            padding: 15px;
        }

        .toast {
            position: fixed;
            top: 15px;
            right: 15px;
            z-index: 9999;
        }

        .bootstrap-basic {
            background: white;
        }

        /* Braintree Hosted Fields styling classes*/
        .braintree-hosted-fields-focused {
            color: #495057;
            background-color: #fff;
            border-color: #80bdff;
            outline: 0;
            box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
        }

        .braintree-hosted-fields-focused.is-invalid {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
        }
    </style>
</head>

<body>
    <!-- Bootstrap inspired Braintree Hosted Fields example -->
    <div class="bootstrap-basic">
        <form id="payment-form" method="post" class="needs-validation" novalidate="">

            <div class="row">
                <div class="col-sm-4 mb-3">
                    <label>
                        Customer Name:
                        <input type="text" name="customerName" class="form-control" id="customerName"
                            value="John Doe">
                    </label>
                    <div class="invalid-feedback">
                        Customer Name on card is required
                    </div>
                </div>
                <div class="col-sm-4 mb-3">
                    <label>
                        Price:
                        <input type="text" name="amount" id="amount" class="form-control" value="9.99">
                    </label>
                    <div class="invalid-feedback">
                        Price is required
                    </div>
                </div>
                <div class="col-sm-4 mb-3">
                    <label>
                        Currency:
                        <select name="currency" class="form-control" id="currency">
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="THB">THB</option>
                            <option value="HKD">HKD</option>
                            <option value="SGD">SGD</option>
                            <option value="AUD">AUD</option>
                        </select>
                    </label>
                    <div class="invalid-feedback">
                        Please select valid currency
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-4 mb-3">
                    <label for="cardNumber">Credit card number</label>
                    <input type="text" name="cardNumber" class="form-control" id="cardNumber">
                    <div class="invalid-feedback">
                        Credit card number is required
                    </div>
                </div>
                <div class="col-sm-4 mb-3">
                    <label for="cc-expiration">Expiration</label>
                    <input type="text" name="cardExpiration" id="cardExpiration" value="10/2022">
                    <div class="invalid-feedback">
                        Expiration date required
                    </div>
                </div>
                <div class="col-sm-4 mb-3">
                    <label for="cc-cvv">CVV</label>
                    <input type="text" name="cardCCV" id="cardCCV" value="123">
                    <div class="invalid-feedback">
                        Security code required
                    </div>
                </div>
            </div>

            <hr class="mb-4">
            <div class="text-center">
                <button class="btn btn-primary btn-lg" type="submit">Pay with <span
                        id="card-brand">Card</span></button>
            </div>
            <input type="hidden" id="nonce" name="payment-method-nonce" value="">

            <!-- Set up the Braintree Drop-in form -->
            <div id="braintree-dropin" style="display: none;"></div>

            <!-- Set up the PayPal button -->
            <div id="paypal-button" style="display: none;"></div>
        </form>
    </div>

</body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.0/js/bootstrap.min.js"></script>

<!-- Load the Braintree and PayPal libraries in the HTML file -->
<script src="https://js.braintreegateway.com/web/dropin/1.22.1/js/dropin.min.js"></script>
<script src="https://www.paypalobjects.com/api/checkout.js"></script>

<!-- Add an event listener to the card number input -->
<script>
    document.querySelector('#cardNumber').addEventListener('input', function(event) {
        // Check if the card number starts with '37' (for AMEX)
        if (event.target.value.startsWith('37')) {
            // Switch to the PayPal gateway
            showPayPalButton();
        } else {
            // Switch back to the Braintree gateway
            showBraintreeDropin();
        }
    });
</script>

<!-- Add an event listener to the currency select box -->
<script>
    document.querySelector('#currency').addEventListener('change', function(event) {
        // Check if the currency is USD, EUR, or AUD
        if (['USD', 'EUR', 'AUD'].includes(event.target.value)) {
            // Switch to the PayPal gateway
            showPayPalButton();
        } else {
            // Switch back to the Braintree gateway
            showBraintreeDropin();
        }
    });
</script>

<!-- Create a function to show the PayPal button -->
<script>
    function showPayPalButton() {
        // Hide the Braintree Drop-in form
        document.querySelector('#braintree-dropin').style.display = 'none';

        // Show the PayPal button
        document.querySelector('#paypal-button').style.display = 'block';
    }
</script>

<!-- Create a function to show the Braintree Drop-in form -->
<script>
    function showBraintreeDropin() {
        // Hide the PayPal button
        document.querySelector('#paypal-button').style.display = 'none';

        // Show the Braintree Drop-in form
        document.querySelector('#braintree-dropin').style.display = 'block';
    }
</script>


<script>
    braintree.dropin.create({
        // Add your Braintree client token here
        authorization: '{{ \Braintree\ClientToken::generate() }}',
        container: '#braintree-dropin'
    }, function(createErr, instance) {
        // Set up the form submission
        document.querySelector('#payment-form').addEventListener('submit', function(event) {
            event.preventDefault();

            instance.requestPaymentMethod(function(requestPaymentMethodErr, payload) {
                // Send the payment method nonce to your server
                const formData = new FormData();
                formData.append('paymentMethodNonce', payload.nonce);

                // Add the other form fields to the request
                formData.append('amount', document.querySelector('#amount').value);
                formData.append('currency', document.querySelector('#currency').value);
                formData.append('customerName', document.querySelector('#customerName').value);

                fetch('/create-transaction', {
                        method: 'POST',
                        body: formData
                    })
                    .then(function(response) {
                        return response.json();
                    })
                    .then(function(responseJson) {
                        // Show a success or error message
                        alert(responseJson.message);
                    });
            });
        });
    });
</script>

<script>
    paypal.Button.render({
        // Add your PayPal client IDs here
        env: 'sandbox',
        client: {
            sandbox: 'AZXFEi0BUvSjdzWUB1c8l2HWDTxeab0qYQPETpnXZq5kowX-i-DXRMNkODi9PPbGvwtR1GFGgsUKqcUw',
            production: 'YOUR_PRODUCTION_CLIENT_ID'
        },
        // Set up the payment
        payment: function(data, actions) {
            return actions.payment.create({
                payment: {
                    transactions: [{
                        amount: {
                            total: document.querySelector('#amount').value,
                            currency: document.querySelector('#currency').value
                        },
                        description: 'Payment for goods or services',
                        custom: document.querySelector('#customerName').value
                    }]
                }
            });
        },
        // Execute the payment
        onAuthorize: function(data, actions) {
            return actions.payment.execute()
                .then(function() {
                    // Show a success message
                    alert('Payment successful!');
                });
        }
    }, '#paypal-button');
</script>

</html>
