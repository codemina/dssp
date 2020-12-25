<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>

    <script src="https://code.jquery.com/jquery-3.5.1.min.js" integrity="sha256-9/aliU8dGd2tb6OSsuzixeV4y/faTqgFtohetphbbj0=" crossorigin="anonymous"></script>

    <style>
        input {
            display: block;
        }
        label {
            display: block;
            margin-bottom: 20px;
        }
        form {
            padding: 10px;
            border: solid 1px;
            width: 400px;
            height: 300px;
            margin: -150px 0 0 -200px;
            position: fixed;
            top: 50%;
            left: 50%;
        }
    </style>
</head>
<body>
    <form method="post" enctype="multipart/form-data" action="engine.php">
        <label>
            PDB 1
            <input type="file" name="pdb1">
        </label>
        <label>
            PDB 2
            <input type="file" name="pdb2">
        </label>
        <label>
            FASTA
            <input type="file" name="fasta">
        </label>
        <button style="width: 100%">Submit</button>
    </form>
</body>
</html>