@extends('frontend.layouts.app')

@section('content')


<button  id="bKash_button" onclick="BkashPayment()">
    Pay with bKash
</button>

    <meta name="csrf-token" content="{{ csrf_token() }}" />

<script src="https://code.jquery.com/jquery-3.4.1.min.js"
        integrity="sha256-CSXorXvZcTkaix6Yvo6HppcZGetbYMGWSFlBw8HfCJo=" crossorigin="anonymous"></script>

@if(config("bkash.sandbox") == true)
<script id="myScript" src="https://scripts.sandbox.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout-sandbox.js"></script>
@else
{{--    This Commented Script for Live Production --}}
    <script id="myScript" src="https://scripts.pay.bka.sh/versions/1.2.0-beta/checkout/bKash-checkout.js"></script>
@endif



<script type="text/javascript">

        $(document).ready(function(){
            $('#bKash_button').trigger('click');
        });
</script>



<script type="text/javascript">
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    function BkashPayment() {
        //showLoading();
        // get token
        $.ajax({
            url: "{{ route('bkash-get-token') }}",
            type: 'POST',
            contentType: 'application/json',
            success: function (data) {
                $('pay-with-bkash-button').trigger('click');
                if (data.hasOwnProperty('msg')) {
                    showErrorMessage(data) // unknown error
                }
            },
            error: function (err) {
                //hideLoading();
                showErrorMessage(err);
            }
        });
    }
    let paymentID = '';
    bKash.init({
        paymentMode: 'checkout',
        paymentRequest: {},
        createRequest: function (request) {
            setTimeout(function () {
                createPayment(request);
            }, 30)
        },
        executeRequestOnAuthorization: function (request) {
            $.ajax({
                url: '{{ route('bkash-execute-payment') }}',
                type: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    "paymentID": paymentID
                }),
                success: function (data) {
                    if (data) {
                        if (data.paymentID != null) {
                            BkashSuccess(data);
                        } else {
                            showErrorMessage(data);
                            bKash.execute().onError();
                        }
                    } else {
                        $.get('{{ route('bkash-query-payment') }}', {
                            payment_info: {
                                payment_id: paymentID
                            }
                        }, function (data) {
                            if (data.transactionStatus === 'Completed') {
                                BkashSuccess(data);
                            } else {
                                createPayment(request);
                            }
                        });
                    }
                },
                error: function (err) {
                    bKash.execute().onError();
                }
            });
        },
        onClose: function () {
            // for error handle after close bKash Popup
        }
    });
    function createPayment(request) {
        // Amount already checked and verified by the controller
        // because of createRequest function finds amount from this request
        request['amount'] = "{{ session()->get('invoice_amount') ?? 100 }}"; // max two decimal points allowed
        $.ajax({
            url: '{{ route('bkash-create-payment') }}',
            data: JSON.stringify(request),
            type: 'POST',
            contentType: 'application/json',
            success: function (data) {
                //hideLoading();
                if (data && data.paymentID != null) {
                    paymentID = data.paymentID;
                    bKash.create().onSuccess(data);
                } else {
                    bKash.create().onError();
                }
            },
            error: function (err) {
               // hideLoading();
                showErrorMessage(err.responseJSON);
                bKash.create().onError();
            }
        });
    }
    function BkashSuccess(data) {
        $.post('{{ route('bkash-success') }}', {
            payment_info: data
        }, function (res) {
            window.location.replace('{{ route('order_confirmed') }}')
        });
    }
    function showErrorMessage(response) {
        let message = 'Unknown Error';
        if (response.hasOwnProperty('errorMessage')) {
            let errorCode = parseInt(response.errorCode);
            let bkashErrorCode = [2001, 2002, 2003, 2004, 2005, 2006, 2007, 2008, 2009, 2010, 2011, 2012, 2013, 2014,
                2015, 2016, 2017, 2018, 2019, 2020, 2021, 2022, 2023, 2024, 2025, 2026, 2027, 2028, 2029, 2030,
                2031, 2032, 2033, 2034, 2035, 2036, 2037, 2038, 2039, 2040, 2041, 2042, 2043, 2044, 2045, 2046,
                2047, 2048, 2049, 2050, 2051, 2052, 2053, 2054, 2055, 2056, 2057, 2058, 2059, 2060, 2061, 2062,
                2063, 2064, 2065, 2066, 2067, 2068, 2069, 503,
            ];
            if (bkashErrorCode.includes(errorCode)) {
                message = response.errorMessage
            }
        }
        alert(message)
    }
</script>
@endsection
