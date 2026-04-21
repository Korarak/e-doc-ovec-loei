require_once 'auth_check.php';
require_role([1]);

include 'edoc-db.php';

if (isset($_GET['id'])) {
    $doc_id = $_GET['id'];

    try {
        $sql = "DELETE FROM documents WHERE doc_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $doc_id);
        $stmt->execute();

        header("Location: doc_manage.php?msg=success");
        exit();
    } catch (mysqli_sql_exception $e) {
        // Error code 1451 is for foreign key constraint fails
        if ($e->getCode() == 1451) {
            header("Location: doc_manage.php?error=fk_error");
        } else {
            header("Location: doc_manage.php?error=db_error");
        }
        exit();
    }
} else {
    header("Location: doc_manage.php");
    exit();
}
?>