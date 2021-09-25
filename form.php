<?php
require 'functions.php';

loggedInCheck();

?>

<!DOCTYPE html>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.1/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-F3w7mX95PdgyTmZZMECAngseQB83DfGTowi0iMjiWaeVhAn4FJkqJByhZMI3AhiU" crossorigin="anonymous">
<html>

<body>
    <div class="container">
        <nav class="navbar navbar-light bg-light">

            <a class="navbar-brand" href="#">
                Bot Tools
            </a>
            <ul class="navbar-nav me-auto mb-2 mb-lg-0 ml-auto">
                <li class="nav-item">
                    <a class="nav-lin" href="https://dev.thebotplatform.com/" target="_blank" rel="noopener noreferrer">Built on top of The Bot Platform API</a>
                &nbsp;
                    <a class="nav-lin" href="logout.php" rel="noopener noreferrer">Logout</a>
                </li>

            </ul>


        </nav>
        <div class="mb-3">

            <h2>Bulk Attribute CSV Upload Form</h2> <br><br> Please upload the CSV file with the attributes you wish to be updated.
            <br><br> Please make sure that the spreadsheet columns are formatted as follows and make sure that you use the attribute name as it appears within The Bot Platform BUT without the $.
            <table class="table">
                <thead>
                    <tr>
                        <th scope="col">Email address</th>
                        <th scope="col">Full name</th>
                        <th scope="col">attribute_name</th>
                        <th scope="col">second_attribute_name</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>dave@thebotplatform.com</td>
                        <td>Dave Smith</td>
                        <td>Laptop</td>
                        <td>Windows</td>
                    </tr>
                    <tr>
                        <td>jenny@thebotplatform.com</td>
                        <td>Jenny Blogs</td>
                        <td>Laptop</td>
                        <td></td>
                    </tr>
                </tbody>
            </table>

            <div class="alert alert-danger" role="alert">
                <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                <span class="sr-only">Warning:</span>
                Max file size: <?php echo ini_get('upload_max_filesize'); ?>
            </div>


            <form action="upload.php" method="post" enctype="multipart/form-data">
                Select file to upload:
                <input type="file" name="fileToUpload" id="fileToUpload"><br><br>
                <input type="submit" value="Upload File" name="submit">
            </form>
        </div>
    </div>
</body>

</html>