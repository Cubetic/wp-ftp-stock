<?
if (isset($_GET['alert'])) {

    $alert = $_GET['alert'];

    echo "<div class='container mt-3'>";

    if ($alert == 0) {
        echo '<div class="alert alert-success alert-dismissible fade show" role="alert">';
        echo "Successfully dowloaded and updated the stock";
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    } elseif ($alert == 1) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo "There was a problem dowlnoading the CSV file";
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    } elseif ($alert == 2) {
        echo '<div class="alert alert-danger alert-dismissible fade show" role="alert">';
        echo "Connection to the FTP server failed";
        echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
    
    echo '
    </div>';

}

?>

<div class="d-flex justify-content-center align-items-center mt-5">
    <h1>FTP Connection Configuration</h1>
</div>

<div class="container">
    <div class="d-flex justify-content-center align-items-center flex-direction-column">

        <form action="/wp-admin/edit.php?post_type=product&page=custom-page" method="post">
            <div class="form-group">
                <label for="server">Server</label>
                <input type="text" class="form-control" id="server" name="server" placeholder="Enter the FTP server" required>
            </div>
            <div class="form-group">
                <label for="user">User</label>
                <input type="text" class="form-control" id="user" name="user" placeholder="Enter FTP user" required>
            </div>
            <div class="form-group">
                <label for="passwd">Password</label>
                <input type="password" class="form-control" id="passwd" name="passwd" placeholder="FTP Password" required>
            </div>
            <div class="form-group">
                <label for="port">Port</label>
                <input type="number" class="form-control" id="port" name="port" placeholder="Default FTP Port is 21">
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-3">Submit</button>
        </form>
    </div>

    <div class="container mt-3">

        <h2>FTP Actual Configuration</h2>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Server</th>
                        <th scope="col">User</th>
                        <th scope="col">Password</th>
                        <th scope="col">Port</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><?= $result ? $result->server : ""; ?></td>
                        <td><?= $result ? $result->user : ""; ?></td>
                        <td><?= $result ? md5($result->passwd) : ""; ?></td>
                        <td><?= $result ? $result->port : ""; ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

    </div>

    <div class="container text-center mt-3">
        <h2>Download Sotck</h2>
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="ftp_stock_download">
            <input type="submit" class="btn btn-primary" value="Download Stock Right Now" <?= $result ? "" : "disabled" ?>>
        </form>
    </div>
</div>