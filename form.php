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
            <h1>Bulk attribute csv upload</h1>
                    <form action="upload.php" method="post" enctype="multipart/form-data">
                        <div class="form-group">
                            Select file to upload:
                            <input type="file" name="fileToUpload" id="fileToUpload">
                        </div>

                        <div class="form-group">
                            <input type="submit" class="btn btn-primary" value="Upload File" name="submit">
                        </div>
                    </form>
        </div>
        <div class="mb-3">


            <div class="alert alert-warning" role="alert">
                <span class="glyphicon glyphicon-exclamation-sign" aria-hidden="true"></span>
                <span class="sr-only">Warning:</span>
                <ul>
                    <li>Use the attribute name as it appears within The Bot Platform but WITHOUT the $</li>
                    <li>Case sensitive</li>
                    <li>Attributes start from the 3rd column</li>
                    <li>Max file size: <?php echo ini_get('upload_max_filesize'); ?>B</li>
                </ul>
            </div>
        </div>

        <div class="mb-3 alert-light" style="opacity:0.4">

            


            
            <hr/>
            <h3>Example CSV</h3>
            <table class="table alert-light">
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

        </div>
    </div>
</body>

</html>