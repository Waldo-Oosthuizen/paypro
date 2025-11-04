<?php

session_start();
// Check if user balances was created, If not create balance
if (!isset($_SESSION['balances'])) {
    $_SESSION['balances'] = [
        'waldo' => 30000,
        'bob' => 150000,
        'alice' => 108000
    ];
}

// Fees array
$fees = [
    'credit_card' => 0.0,
    'paypal' => 0.024,
    'crypto' => 0.032,
    'bitcoin' => 0.040
];

// Session Array to store transactions
if (!isset($_SESSION['transactions'])) {
    $_SESSION['transactions'] = [];
}

// Keep track of user
if (isset($_POST['user_select'])) {
    $_SESSION['selected_user'] = $_POST['user_select'];
}

// selected user
$selected_user = $_SESSION['selected_user'] ?? null;


// Message for success
$message = '';
// Message for error
$error = '';

// When for submits
if ($_SERVER['REQUEST_METHOD'] == "POST") {

    // Currrent Balance
    if ($selected_user) {
        $current_balance = $selected_user ? ($_SESSION['balances'][$selected_user] ?? null) : null;
    }

    // Payment form
    if (isset($_POST['make_payment'])) {
        $amount = $_POST['payment_amount'];
        $method = $_POST['payment_method'] ?? '';
        $user = $_POST['user_select'];
        $transaction_id = uniqid();
        $is_valid = false;

        if (!empty($user) && isset($_SESSION['balances'][$user])) {
            // Check if a method has been selected
            if ($method == '') {
                $error = 'Please choose a payment method. ';
            }
            // Bitcoin is not supported as per spec. 
            else if ($method == 'bitcoin') {
                $error = 'Bitcoin is not supported, choose another payment method. ';
                $is_valid = false;
            }
            // Check for fraud
            else if ($amount >= 100000) {
                $error = 'Fraud Detected! Cannot make payments that are R1000.000 or more. Payment rejected.  ';
                $is_valid = false;
            }
            // If payment amount is greater than what is available.
            else if ($amount > $current_balance) {
                $error = "There is not enough money in this account for this transaction, current balance is R$current_balance";
                $is_valid = false;
            }

            // If amount is negative
            else if ($amount <= 0) {
                $error = "You cannot make payments that are less than or 0. ";
                $is_valid = false;
            } else {
                // Surcharge
                $fee_rate = $fees[$method];
                $surcharge = $amount * $fee_rate;
                // Total
                $total = $amount + $surcharge;
                // Deduct
                $_SESSION['balances'][$user] -= $total;
                // valid
                $is_valid = true;
            }
        } else {

            $message = "Error: Cannot find user. ";
        }

        if ($is_valid) {
            // Capture transactions
            $_SESSION['transactions'][] = [
                'ID' => $transaction_id,
                'user' => $user,
                'method' => $method,
                'fee' => $fee_rate,
                'surcharge' => $surcharge,
                'amount' => $amount,
                'total' => $total
            ];

            $message = " R$total was successfully deducted from "  . ucfirst($user) . "'s account. ($transaction_id)";
        }
    }

    // Refund form
    if (isset($_POST['refund'])) {
        $user = $_POST['user_select'];
        $refund_id = $_POST['refund_id'];
        $found = false;

        // Search IDs
        foreach ($_SESSION['transactions'] as $index => $txt) {
            if ($txt['ID'] == $refund_id && $txt['user'] == $user) {

                // Refund the amount to user's balance
                $_SESSION['balances'][$user] += $txt['total'];

                // remove the transaction from the array
                unset($_SESSION['transactions'][$index]);

                // reindex array to make sure indexes are sequential
                $_SESSION['transactions'] = array_values($_SESSION['transactions']);

                $message = "ID matches! Transaction $refund_id refunded R{$txt['total']} added backed to $user's account. ";
            } else if ($txt['user'] != $user) {
                if (!$found) {
                    $error = "ID does not match any transactions, try again. ";
                }
            }
        }
    }

    // Updated balance
    if ($selected_user) {
        $current_balance = $selected_user ? ($_SESSION['balances'][$selected_user] ?? null) : null;
    }
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PayPro</title>
</head>
<style>
    /*Default styling */
    html,
    body {
        width: 100%;
        overflow-x: hidden;
    }

    body {
        font-family: 'Inter', sans-serif;
        padding: 1rem;
        margin: 0;
        box-sizing: border-box;
        background-image: url('img/payment_bg.png');
        /* Background from canva */
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
    }

    /* Table styling */
    table {
        border-collapse: collapse;
    }


    .long_div table {
        overflow-x: auto;
        overflow-x: auto;
        width: 100%;
        min-width: 100%;
    }

    table th {
        background-color: #0070BA;
        color: #F4F7FA;
        padding: 12px 15px;
        text-align: left;
    }

    table td {
        padding: 12px 15px;
        text-align: left;
    }

    tbody tr:nth-child(even) {
        background-color: #dadcdfff;
    }


    h2 {
        font-size: clamp(1.2rem, 2vw + 1rem, 2rem);
        line-height: 28px;
    }

    /* Reusable flex class*/

    .flex_col {
        display: flex;
        flex-direction: column;
    }

    /* Form */
    select {
        padding: 15px;
        font-size: 28px;
        border-radius: 5px;
        width: 60%;
        text-align: left;
        border: 2px solid #bbbbbbff;
        cursor: pointer;
        margin-bottom: 10px;
        background-attachment: fixed;
    }

    /* main container for UI */
    .container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 1rem;
    }

    .container .long_div,
    .forms {
        font-size: 1rem;
        text-align: left;
        padding: 1.5rem;
        line-height: 2;
        border-radius: 5px;
        box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.2);
    }

    .forms,
    .long_div {
        background-color: rgba(255, 255, 255, 0.3);
        border-left: 10px solid #bbbbbbff;
    }

    /* Balance container*/
    .long_div {
        grid-column: span 2;
    }

    /* Reusable form grid*/
    .form_grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .form_type {
        border: 2px solid #bbbbbbff;
        padding: 5px;
        border-radius: 5px;
    }

    .no_border {
        border: 0px;
    }

    button {
        padding: 1rem;
        font-size: 1.1rem;
        border-radius: 8px;
        cursor: pointer;
        width: 100%;
        border: none;
        box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.3);
        color: white;
    }

    button:hover {
        box-shadow: 3px 3px 5px rgba(0, 0, 0, 0.2);
    }

    .payment_btn {
        background-color: #56CCF2;
        padding: 20px;
        font-size: 22px;
    }

    .refund_btn {
        background-color: #f2cc58ff;
        padding: 20px;
        font-size: 22px;
    }

    .payment_logos {
        width: 40px;
        height: 40px;
        object-fit: contain;
    }

    input[type="radio"] {
        display: none;
    }



    .payment_type {
        justify-content: space-between;
        text-align: left;
        transition: 0.3s ease;
        cursor: pointer;
        align-items: center;
        transition: 0.2s ease-in-out;
        min-height: 70px;
    }

    .payment_type:hover {
        border: 2px solid #0070BA;
        background-color: #0070BA;
        color: #F4F7FA;

    }


    .payment_type input[type="radio"]:checked+img,
    .payment_type input[type="radio"]:checked+span,
    .payment_type:has(input[type="radio"]:checked) {
        border-color: #0070BA;
        background-color: #0070BA;
        color: #F4F7FA;

    }

    /* Return form*/
    .input_boxes {
        padding: 15px;
        font-size: 32px;
        border-radius: 5px;
        width: 95%;
        text-align: left;
        border: 2px solid #dadcdfff;
        margin-bottom: 5px;
    }



    /* Tablet layout up to 900px wide */
    @media (max-width: 900px) {
        .container {
            grid-template-columns: repeat(2, 1fr);
        }

        .long_div {
            grid-column: span 2;
        }

        .forms {
            grid-column: span 2;
        }

        .form_grid {
            grid-template-columns: auto;
        }

        select {
            width: 100%;
        }
    }


    @media (max-width: 600px) {

        /* Hide the table headers */
        table .table_header {
            display: none;
        }

        table,
        table tbody,
        table tr,
        table td {
            display: block;
            width: 95%;
        }

        table tr {
            margin-bottom: 1rem;
            border: 1px solid #ccc;
            border-radius: 8px;
            background-color: rgba(255, 255, 255, 0.6);
            padding: 10px;
        }

        table td {
            text-align: left;
            padding: 8px 12px;
            position: relative;
        }

        /* Add labels before each cell using data-label attribute */
        table td::before {
            content: attr(data-label);
            font-weight: bold;
            display: block;
            color: #0070BA;
            margin-bottom: 4px;
        }

        .input_boxes {
            width: 92%;
        }
    }






    /* CSS grid learned from https://www.w3schools.com/css/css_grid.asp */
    /* PayPal Logo gotten from: https://newsroom.paypal-corp.com/media-resources */
    /* Credit card logo gotten from https://corporate.visa.com/en/about-visa/mediakits.html */
    /* Bitcoin logo gotten from https://cryptologos.cc/bitcoin */
    /* crypto logo gotten from canva */
</style>


<body>
    <div class="container">
        <!-- Messages -->
        <?php if ($message != '') echo "<div id='success_message' class='long_div' style='border-left: 10px solid #27ae60; background-color: rgba(39, 174, 96, 0.2)
'>" . $message . "</div>" ?>

        <!-- Errors -->
        <?php if ($error != '') echo "<div id='success_message' class='long_div' style='border-left: 10px solid #A2584C; background-color: rgba(162, 88, 76, 0.2)
'>" . $error . "</div>" ?>
        <!-- Select user div -->
        <div class="forms" style="display: flex; flex-direction: column;">
            <h2>Select User's Account:</h2>
            <!-- Force the form to submit when selecting a user -->
            <form method="post" action="">
                <select id="user_select" name="user_select" required onchange="this.form.submit()">
                    <option value="">Select User</option>
                    <option value="waldo" <?php if ($selected_user == 'waldo') echo 'selected'; ?>>Waldo</option>
                    <option value="bob" <?php if ($selected_user == 'bob') echo 'selected'; ?>>Bob</option>
                    <option value="alice" <?php if ($selected_user == 'alice') echo 'selected'; ?>>Alice</option>
                </select>
            </form>
        </div>

        <!-- Select user div -->
        <div class="forms">
            <?php if (isset($current_balance)): ?>
                <h2><?php echo ucfirst($selected_user) . "'s Current Balance is:" ?></h2>
                <h2>R<?php echo number_format($current_balance, 2); ?></h2>
            <?php else: ?>
                <h2>Please select a user to view balance</h2>
            <?php endif; ?>
        </div>



        <div class="forms">
            <h2>Payments</h2>
            <form action="" method="post">
                <!--Hidden form to get selected user -->
                <input type="hidden" name="user_select" value="<?php echo $selected_user;  ?>" required>
                <div>
                    <!-- Payment methods user can select -->
                    <label for="Payment_method">Select your preferred payment method:</label>
                    <div class="form_grid">
                        <label class="form_type payment_type">
                            <input type="radio" id="credit_card" name="payment_method" value="credit_card">
                            <img class="payment_logos" src="logos/visa-brand-symbol-1000x668.webp">
                            Credit Card
                        </label>

                        <label class="form_type payment_type">
                            <input type="radio" id="paypal" name="payment_method" value="paypal">
                            <img class="payment_logos" src="logos/PayPal-Monogram-FullColor-RGB.png">
                            PayPal
                        </label>

                        <label class="form_type payment_type">
                            <input type="radio" id="crypto" name="payment_method" value="crypto">
                            <img class="payment_logos" src="logos/crypto.png">
                            Cryptocurrency
                        </label>

                        <label class="form_type payment_type">
                            <input type="radio" id="bitcoin" name="payment_method" value="bitcoin">
                            <img class="payment_logos" src="logos/bitcoin-btc-logo.png">
                            Bitcoin
                        </label>

                    </div>

                    <div class="flex_col">
                        <label>Payment Amount:</label>
                        <input class="input_boxes" type="number" name="payment_amount" required>
                    </div>
                </div>


                <div class="form_type no_border">
                    <button type="submit" class="payment_btn" name="make_payment">Make Payment</button>
                </div>

            </form>
        </div>

        <div class="forms">
            <h2>Refunds</h2>
            <form action="" method="post">
                <!--Hidden form to get selected user -->
                <input type="hidden" name="user_select" value="<?php echo $selected_user; ?>">
                <div class="flex_col">
                    <label>Transaction ID of payment you want refunded:</label>
                    <input class="input_boxes" type="text" name="refund_id">
                </div>

                <div class="flex_col" style="padding-bottom: 16px">
                    <label>Reason for refund:</label>
                    <textarea class="input_boxes" rows="3" cols="30" name="refund_reason"></textarea>
                </div>

                <div class="form_type no_border">
                    <button type="submit" class="refund_btn" name="refund">Request Refund</button>
                </div>
            </form>
        </div>

        <div class="long_div">
            <h2>User's Transaction History:</h2>
            <table cellpadding="5" cellspacing="0">
                <tr class="table_header">
                    <th>ID</th>
                    <th>Username</th>
                    <th>Method</th>
                    <th>Fee</th>
                    <th>Surcharge</th>
                    <th>Amount</th>
                    <th>Total</th>
                </tr>
                <?php foreach ($_SESSION['transactions'] as $txt): ?>
                    <tr>
                        <td data-label="ID"><?php echo htmlspecialchars($txt['ID']); ?></td>
                        <td data-label="user"><?php echo htmlspecialchars($txt['user']); ?></td>
                        <td data-label="method"><?php echo htmlspecialchars($txt['method']); ?></td>
                        <td data-label="fee"><?php echo "R" . htmlspecialchars($txt['fee']); ?></td>
                        <td data-label="surcharge"><?php echo "R" . htmlspecialchars($txt['surcharge']); ?></td>
                        <td data-label="amount"><?php echo "R" . htmlspecialchars($txt['amount']); ?></td>
                        <td data-label="total"><?php echo "R" . htmlspecialchars($txt['total']); ?></td>
                    </tr>
                <?php endforeach ?>
            </table>
        </div>

    </div>
    <script>
        // Wait a few seconds, then hide messages smoothly
        setTimeout(() => {
            const success = document.getElementById('success_message');
            const error = document.getElementById('error_message');

            if (success) {
                success.style.transition = "opacity 0.5s ease";
                success.style.opacity = "0";
                setTimeout(() => success.remove(), 500); // remove from DOM after fade
            }

            if (error) {
                error.style.transition = "opacity 0.5s ease";
                error.style.opacity = "0";
                setTimeout(() => error.remove(), 500);
            }
        }, 10000); // 4 seconds before fading out
    </script>
</body>

</html>