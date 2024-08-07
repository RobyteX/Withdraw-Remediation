<?php
$sem_key = ftok(__FILE__, 'a');
$sem_id = sem_get($sem_key, 1);

if ($sem_id === false) {
    die("Failed to create or get semaphore.");
}

$mysqli = new mysqli("localhost", "root", "StrongP@ssw0rd!", "bank");
if ($mysqli->connect_error) die("Connection failed: " . $mysqli->connect_error);

$withdraw_amount = 100.0;

try {
    if (!sem_acquire($sem_id)) {
        throw new Exception("Could not acquire the semaphore.");
    }

    $mysqli->begin_transaction();

    $stmt = $mysqli->prepare("SELECT balance FROM accounts WHERE id = 1 FOR UPDATE");
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Account not found.");
    }

    $row = $result->fetch_assoc();
    $current_balance = $row['balance'];

    if ($current_balance >= $withdraw_amount) {
        $new_balance = $current_balance - $withdraw_amount;

        $stmt = $mysqli->prepare("UPDATE accounts SET balance = ? WHERE id = 1");
        $stmt->bind_param("d", $new_balance);
        $stmt->execute();

        $stmt = $mysqli->prepare("INSERT INTO logs (action, amount) VALUES ('withdraw', ?)");
        $stmt->bind_param("d", $withdraw_amount);
        $stmt->execute();

        $mysqli->commit();
        echo "Withdrawal successful. New balance: $new_balance";
    } else {
        throw new Exception("Insufficient funds.");
    }

} catch (Exception $e) {
    $mysqli->rollback();
    echo "Transaction failed: " . $e->getMessage();
} finally {
    if ($sem_id && !sem_release($sem_id)) {
        echo "Failed to release the semaphore.";
    }
    $mysqli->close();
}
?>