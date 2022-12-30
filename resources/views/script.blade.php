<script>
    var form = document.querySelector('#payment-form');
    braintree.client.create({
        authorization: '{{ \Braintree\ClientToken::generate() }}'
    }, function(err, clientInstance) {
        if (err) {
            console.error(err);
            return;
        }
        braintree.hostedFields.create({
            client: clientInstance,
            styles: {
                input: {
                    // change input styles to match
                    // bootstrap styles
                    'font-size': '1rem',
                    color: '#495057'
                }
            },
            fields: {
                cardholderName: {
                    selector: '#cc-name',
                    placeholder: 'Name as it appears on your card'
                },
                number: {
                    selector: '#cc-number',
                    placeholder: '4111 1111 1111 1111'
                },
                cvv: {
                    selector: '#cc-cvv',
                    placeholder: '123'
                },
                expirationDate: {
                    selector: '#cc-expiration',
                    placeholder: 'MM / YY'
                }
            }
        }, function(err, hostedFieldsInstance) {
            if (err) {
                console.error(err);
                return;
            }

            function createInputChangeEventListener(element) {
                return function() {
                    validateInput(element);
                }
            }

            function setValidityClasses(element, validity) {
                if (validity) {
                    element.removeClass('is-invalid');
                    element.addClass('is-valid');
                } else {
                    element.addClass('is-invalid');
                    element.removeClass('is-valid');
                }
            }

            function validateInput(element) {
                // very basic validation, if the
                // fields are empty, mark them
                // as invalid, if not, mark them
                // as valid
                if (!element.val().trim()) {
                    setValidityClasses(element, false);
                    return false;
                }
                setValidityClasses(element, true);
                return true;
            }


            function validateAmount() {
                var baseValidity = validateInput(amount);
                if (!baseValidity) {
                    return false;
                }
                setValidityClasses(amount, true);
                return true;
            }

            function validateCurrency() {
                var baseValidity = validateInput(currency);
                if (!baseValidity) {
                    return false;
                }
                setValidityClasses(currency, true);
                return true;
            }

            var ccName = $('#cc-name');
            var amount = $('#amount');
            var currency = $('#currency');
            ccName.on('change', function() {
                validateInput(ccName);
            });

            amount.on('change', validateAmount);
            currency.on('change', validateCurrency);

            hostedFieldsInstance.on('validityChange', function(event) {
                var field = event.fields[event.emittedBy];
                // Remove any previously applied error or warning classes
                $(field.container).removeClass('is-valid');
                $(field.container).removeClass('is-invalid');
                if (field.isValid) {
                    $(field.container).addClass('is-valid');
                } else if (field.isPotentiallyValid) {
                    // skip adding classes if the field is
                    // not valid, but is potentially valid
                } else {
                    $(field.container).addClass('is-invalid');
                }
            });

            hostedFieldsInstance.on('cardTypeChange', function(event) {

                var cardBrand = $('#card-brand');
                var cvvLabel = $('[for="cc-cvv"]');

                if (event.cards.length === 1) {
                    var card = event.cards[0];

                    if (card.type == 'american-express') {
                        // Switch to the PayPal gateway
                        showPayPalButton();
                    } else {

                        // showBraintreeButton();
                        // change pay button to specify the type of card
                        // being used
                        cardBrand.text(card.niceType);
                        // update the security code label
                        cvvLabel.text(card.code.name);

                    }

                } else {
                    // reset to defaults
                    cardBrand.text('Card');
                    cvvLabel.text('CVV');
                    showBraintreeButton();
                }
            });

            //
            document.querySelector('#payment-form').addEventListener('submit', function(event) {
                event.preventDefault(); // Prevent the form from submitting

                event.preventDefault();

                var formIsInvalid = false;
                var state = hostedFieldsInstance.getState();

                if (!validateAmount()) {
                    formIsInvalid = true;
                }
                if (!validateCurrency()) {
                    formIsInvalid = true;
                }

                // Loop through the Hosted Fields and check
                // for validity, apply the is-invalid class
                // to the field container if invalid
                Object.keys(state.fields).forEach(function(field) {
                    if (!state.fields[field].isValid) {
                        $(state.fields[field].container).addClass('is-invalid');
                        formIsInvalid = true;
                    }
                });

                if (formIsInvalid) {
                    // skip tokenization request if any fields are invalid
                    return;
                }

                // Collect the form data
                const formData = new FormData(event.target);

                // Use Braintree
                hostedFieldsInstance.tokenize(function(tokenizeErr, payload) {
                    if (tokenizeErr) {
                        // Handle errors
                        return;
                    }

                    // Add the payment method nonce to the form data
                    formData.append('paymentMethodNonce', payload.nonce);

                    // Send the form data to the server
                    fetch('/payments/braintree', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-CSRF-TOKEN': document.head.querySelector(
                                    'meta[name="csrf-token"]').content
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            console.log(data);
                        });

                    // Show a success message
                    alert('Payment successful!');
                    $('.toast').toast('show')
                });

            });

            //
        });
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
            showBraintreeButton();
        }
    });
</script>


<!-- Create a function to show the PayPal button -->
<script>
    function showPayPalButton() {
        // Hide the Braintree button
        document.querySelector('#braintree-button').style.display = 'none';

        // Show the PayPal button
        document.querySelector('#paypal-button').style.display = 'block';
    }
</script>


<!-- Create a function to show the Braintree payment button -->
<script>
    function showBraintreeButton() {
        // Hide the PayPal button
        document.querySelector('#paypal-button').style.display = 'none';

        // Show the Braintree button
        document.querySelector('#braintree-button').style.display = 'block';
    }
</script>

<script>
    paypal.Button.render({
        // Add your PayPal client IDs here
        env: 'sandbox',
        client: {
            sandbox: "{{ env('PAYPAL_SANDBOX_CLIENT_ID') }}",
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
                    // Send the form data and PayPal response to the Laravel route using an AJAX request
                    const formData = new FormData(document.querySelector('#payment-form'));
                    formData.append('paypalResponse', JSON.stringify(data));

                    fetch('/payments/paypal', {
                            method: 'POST',
                            body: formData,
                            headers: {
                                'X-CSRF-TOKEN': document.head.querySelector(
                                    'meta[name="csrf-token"]').content
                            }
                        })
                        .then(response => response.json())
                        .then(data => {
                            console.log(data);
                        });

                    // Show a success message
                    alert('Payment successful!');
                    $('.toast').toast('show')
                });
        }
    }, '#paypal-button');
</script>
