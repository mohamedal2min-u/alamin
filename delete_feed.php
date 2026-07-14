
$feed = \App\Models\FlockFeedLog::find(30);
if ($feed && is_null($feed->inventory_transaction_id)) {
    $feed->delete();
    echo "Deleted feed log 30 successfully.\n";
} else {
    echo "Feed log 30 not found or has transaction.\n";
}
