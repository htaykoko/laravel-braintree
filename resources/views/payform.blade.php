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
        <form id="payment-form" method="post" class="needs-validation" novalidate>

            <div class="row">
                <div class="col-sm-6 mb-3">
                    <label for="cc-name">Cardholder Name</label>
                    <div class="form-control" id="cc-name"></div>
                    <small class="text-muted">Full name as displayed on card</small>
                    <div class="invalid-feedback">
                        Name on card is required
                    </div>
                </div>
                <div class="col-sm-3 mb-3">
                    <label for="amount">Amount</label>
                    <input type="number" min="1" name='amount' class="form-control" id="amount"
                        placeholder="10">
                    <div class="invalid-feedback">
                        Amount is required
                    </div>
                </div>
                <div class="col-sm-3 mb-3">
                    <label for="currency">Currency</label>
                    <select name="currency" id="currency" class="form-control">
                        <option value="USD">USD</option>
                        <option value="EUR">EUR</option>
                        <option value="THB">THB</option>
                        <option value="HKD">HKD</option>
                        <option value="SGD">SGD</option>
                        <option value="AUD">AUD</option>
                    </select>
                    <div class="invalid-feedback">
                        Please enter a valid email address for shipping updates.
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-sm-3 mb-3">
                    <label>Customer Name:</label>
                    <input type="text" class="form-control" name="customerName" id="customerName"
                        placeholder="John Doe" required>
                    <div class="invalid-feedback">
                        Customer Name is required
                    </div>
                </div>
                <div class="col-sm-3 mb-3">
                    <label for="cc-number">Credit card number</label>
                    <div class="form-control" id="cc-number"></div>
                    <div class="invalid-feedback">
                        Credit card number is required
                    </div>
                </div>
                <div class="col-sm-3 mb-3">
                    <label for="cc-expiration">Expiration</label>
                    <div class="form-control" id="cc-expiration"></div>
                    <div class="invalid-feedback">
                        Expiration date required
                    </div>
                </div>
                <div class="col-sm-3 mb-3">
                    <label for="cc-cvv">CVV</label>
                    <div class="form-control" id="cc-cvv"></div>
                    <div class="invalid-feedback">
                        Security code required
                    </div>
                </div>
            </div>

            <hr class="mb-4">
            <div class="text-center" id="braintree-button">
                <button class="btn btn-primary btn-lg" type="submit">Pay with <span
                        id="card-brand">Card</span></button>
            </div>
            <input type="hidden" id="nonce" name="payment-method-nonce" value="">

            <!-- Add the PayPal button to the form -->
            <div class="text-center" id="paypal-button" style="display: none"></div>
        </form>
    </div>
    <div aria-live="polite" aria-atomic="true" style="position: relative; min-height: 200px;">
        <div class="toast" role="alert" aria-live="assertive" aria-atomic="true" data-autohide="false">
            <div class="toast-header">
                <strong class="mr-auto">Success!</strong>
                <small>Just now</small>
                <button type="button" class="close ml-2 mb-1" data-dismiss="toast" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="toast-body">
                Payment Successful.
                <a href="{{ route('welcome') }}">Click me</a>
            </div>
        </div>
    </div>
</body>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.0/js/bootstrap.min.js"></script>

<script src="https://js.braintreegateway.com/web/3.88.6/js/hosted-fields.min.js"></script>
<!-- Load the client component. -->
<script src="https://js.braintreegateway.com/web/3.88.6/js/client.min.js"></script>

<!-- Load the PayPal libraries -->
<script src="https://www.paypalobjects.com/api/checkout.js"></script>

@include('script')

</html>
