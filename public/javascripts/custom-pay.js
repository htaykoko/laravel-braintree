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
        },  function(err, hostedFieldsInstance) {
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


    function validateAmount () {
                var baseValidity = validateInput(amount);
    if (!baseValidity) {
                    return false;
                }
    setValidityClasses(amount, true);
    return true;
            }

    function validateCurrency () {
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
    // change pay button to specify the type of card
    // being used
    cardBrand.text(card.niceType);
    // update the security code label
    cvvLabel.text(card.code.name);

                } else {
        // reset to defaults
        cardBrand.text('Card');
    cvvLabel.text('CVV');
                }
            });

    //
    document.querySelector('#payment-form').addEventListener('submit', function (event) {
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
    hostedFieldsInstance.tokenize(function (tokenizeErr, payload) {
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
        'X-CSRF-TOKEN': document.head.querySelector('meta[name="csrf-token"]').content
                        }
                    })
    .then(function (response) {
        $('.toast').toast('show')
    });
                });

            });

            //
        });
    });

