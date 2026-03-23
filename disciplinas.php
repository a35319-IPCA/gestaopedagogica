<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <h1>Disciplinas</h1>
    <?php
        $servername = "localhost";
        $username = "root";
        $password = "";
        $dbname = "ipca-vnf";

        // Create connection
        $conn = new mysqli($servername, $username, $password, $dbname);

        // Check connection
        if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
        }
        echo "Connected successfully";
        $sql = "SELECT * FROM Disciplinas";
        echo "<br>";
        // Execute the SQL query
        $result = $conn->query($sql);

        // Process the result set
        if ($result->num_rows > 0) {
        // Output data of each row
        while($row = $result->fetch_assoc()) {
            echo "id: " . $row["ID"]. " - Disciplina: " . $row["nome_disciplina"]. " - Sigla: " . $row["Sigla"]. "<br>";
        }
        } else {
        echo "0 results";
        }

?>
</body>
</html>