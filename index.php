<?php
// Allow cross-origin requests (optional)
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$action = $_GET['action'] ?? '';
switch ($action) {
    case 'getBooks':
        if (file_exists("Books.xml")) {
            echo file_get_contents("Books.xml");
        } else {
            echo json_encode(["message" => "Books.xml not found"]);
        }
        break;

    case 'addBook':
        $data = json_decode(file_get_contents("php://input"), true);
        if ($data) {
            addBook($data);
        } else {
            echo json_encode(["message" => "Invalid data"]);
        }
        break;

    case 'getAuthors':
        getAuthors();
        break;

    default:
        echo json_encode(["message" => "Action not found"]);
}

function addBook($data) {
    // Validate required fields
    if (empty($data['ID']) || empty($data['Title']) || empty($data['AuthorID'])) {
        echo json_encode(["message" => "Missing required fields"]);
        return;
    }

    // Validate if AuthorID exists
    if (!isAuthorExists($data['AuthorID'])) {
        echo json_encode(["message" => "Author not found"]);
        return;
    }

    // Validate if BorrowerID exists, if provided
    if (!empty($data['BorrowerID']) && !isBorrowerExists($data['BorrowerID'])) {
        echo json_encode(["message" => "Borrower not found"]);
        return;
    }

    // Load XML file
    $xml = simplexml_load_file("Books.xml");
    if ($xml === false) {
        echo json_encode(["message" => "Error loading Books.xml"]);
        return;
    }

    // Add new book entry
    $newBook = $xml->addChild('Book');
    $newBook->addChild('ID', htmlspecialchars($data['ID']));
    $newBook->addChild('Title', htmlspecialchars($data['Title']));
    $newBook->addChild('AuthorID', htmlspecialchars($data['AuthorID']));
    $newBook->addChild('BorrowerID', htmlspecialchars($data['BorrowerID'] ?? ''));
    $newBook->addChild('IsAvailable', $data['IsAvailable'] ? 'true' : 'false');

    // Format the XML for better readability
    $dom = new DOMDocument();
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());

    // Save formatted XML file
    if ($dom->save("Books.xml")) {
        // If borrower is specified, update Borrower's borrowed books and book availability
        if (!empty($data['BorrowerID'])) {
            updateBorrowerBooks($data['BorrowerID'], $data['ID']);
            updateBookAvailability($data['ID'], false); // Mark the book as borrowed
        }
        echo json_encode(["message" => "Book added successfully"]);
    } else {
        echo json_encode(["message" => "Failed to save book"]);
    }
}

// Function to retrieve authors
function getAuthors() {
    if (!file_exists("Authors.xml")) {
        echo json_encode(["message" => "Authors.xml not found"]);
        return;
    }

    $xml = simplexml_load_file("Authors.xml");
    if ($xml === false) {
        echo json_encode(["message" => "Error loading Authors.xml"]);
        return;
    }

    $authors = [];
    foreach ($xml->Author as $author) {
        $authors[] = [
            "ID" => (string)$author->ID,
            "Name" => (string)$author->Name
        ];
    }

    echo json_encode($authors);
}

// Check if the Author exists
function isAuthorExists($authorID) {
    $authorsXml = simplexml_load_file("Authors.xml");
    foreach ($authorsXml->Author as $author) {
        if ((string) $author->ID == $authorID) {
            return true;
        }
    }
    return false;
}

// Check if the Borrower exists
function isBorrowerExists($borrowerID) {
    $borrowersXml = simplexml_load_file("Borrowers.xml");
    foreach ($borrowersXml->Borrower as $borrower) {
        if ((string) $borrower->ID == $borrowerID) {
            return true;
        }
    }
    return false;
}

// Update Borrower's borrowed books list
function updateBorrowerBooks($borrowerID, $bookID) {
    $borrowersXml = simplexml_load_file("Borrowers.xml");
    foreach ($borrowersXml->Borrower as $borrower) {
        if ((string) $borrower->ID == $borrowerID) {
            $borrower->BorrowedBooks->addChild('BookID', $bookID);
            $borrowersXml->asXML("Borrowers.xml");
            break;
        }
    }
}

// Update the availability of the book in Books.xml
function updateBookAvailability($bookID, $isAvailable) {
    $xml = simplexml_load_file("Books.xml");
    foreach ($xml->Book as $book) {
        if ((string) $book->ID == $bookID) {
            $book->IsAvailable = $isAvailable ? 'true' : 'false';
            $xml->asXML("Books.xml");
            break;
        }
    }
}
?>
