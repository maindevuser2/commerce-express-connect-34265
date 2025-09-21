<?php
namespace Controllers;

require_once __DIR__ . '/../models/Book.php';
require_once __DIR__ . '/../config/Database.php';

use Models\Book;
use Exception;
class BookController {
    private $bookModel;
    private $db;

    public function __construct() {
        $database = new \Database();
        $this->db = $database->getConnection();
        $this->bookModel = new Book($this->db);
    }

    // Manejar las acciones via GET/POST
    public function handleRequest() {
        $action = $_GET['action'] ?? $_POST['action'] ?? 'index';
        
        switch ($action) {
            case 'index':
                $this->index();
                break;
            case 'create':
                $this->create();
                break;
            case 'store':
                $this->store($_POST);
                break;
            case 'edit':
                $id = $_GET['id'] ?? 0;
                $this->edit($id);
                break;
            case 'update':
                $id = $_POST['id'] ?? 0;
                $this->update($id, $_POST);
                break;
            case 'delete':
                $id = $_POST['id'] ?? 0;
                $this->delete($id);
                break;
            case 'toggle_status':
                $id = $_POST['id'] ?? 0;
                $this->toggleStatus($id);
                break;
            case 'toggle_featured':
                $id = $_POST['id'] ?? 0;
                $this->toggleFeatured($id);
                break;
            case 'details':
                $id = $_GET['id'] ?? 0;
                $this->getBookDetails($id);
                break;
            default:
                $this->index();
                break;
        }
    }

    // Mostrar lista de libros
    public function index() {
        try {
            $books = $this->bookModel->getAll();
            $stats = [
                'total_books' => $this->bookModel->count(),
                'featured_books' => $this->bookModel->countFeatured(),
                'total_categories' => count($this->bookModel->getCategories())
            ];
            
            include __DIR__ . '/../views/admin/books.php';
        } catch (Exception $e) {
            error_log("Error en BookController::index: " . $e->getMessage());
            AuthController::setFlashMessage('Error al cargar los libros', 'error');
            header('Location: index.php?page=admin&action=dashboard');
            exit();
        }
    }

    // Mostrar formulario de creación
    public function create() {
        include __DIR__ . '/../views/admin/book-form.php';
    }

    // Procesar creación de libro
    public function store($data) {
        try {
            // Validar datos requeridos
            $errors = $this->validateBookData($data);
            if (!empty($errors)) {
                AuthController::setFlashMessage(implode(', ', $errors), 'error');
                return false;
            }

            // Crear el libro
            $result = $this->bookModel->create($data);
            if ($result) {
                AuthController::setFlashMessage('Libro creado exitosamente', 'success');
                return true;
            } else {
                AuthController::setFlashMessage('Error al crear el libro', 'error');
                return false;
            }
        } catch (Exception $e) {
            error_log("Error en BookController::store: " . $e->getMessage());
            AuthController::setFlashMessage('Error interno del servidor', 'error');
            return false;
        }
    }

    // Mostrar formulario de edición
    public function edit($id) {
        try {
            $book = $this->bookModel->getById($id);
            if (!$book) {
                AuthController::setFlashMessage('Libro no encontrado', 'error');
                header('Location: index.php?page=admin&action=books');
                exit();
            }
            
            include __DIR__ . '/../views/admin/book-form.php';
        } catch (Exception $e) {
            error_log("Error en BookController::edit: " . $e->getMessage());
            AuthController::setFlashMessage('Error al cargar el libro', 'error');
            header('Location: index.php?page=admin&action=books');
            exit();
        }
    }

    // Procesar actualización de libro
    public function update($id, $data) {
        try {
            // Verificar que el libro existe
            $book = $this->bookModel->getById($id);
            if (!$book) {
                AuthController::setFlashMessage('Libro no encontrado', 'error');
                return false;
            }

            // Validar datos
            $errors = $this->validateBookData($data);
            if (!empty($errors)) {
                AuthController::setFlashMessage(implode(', ', $errors), 'error');
                return false;
            }

            // Actualizar el libro
            $result = $this->bookModel->update($id, $data);
            if ($result) {
                AuthController::setFlashMessage('Libro actualizado exitosamente', 'success');
                return true;
            } else {
                AuthController::setFlashMessage('Error al actualizar el libro', 'error');
                return false;
            }
        } catch (Exception $e) {
            error_log("Error en BookController::update: " . $e->getMessage());
            AuthController::setFlashMessage('Error interno del servidor', 'error');
            return false;
        }
    }

    // Eliminar libro
    public function delete($id) {
        try {
            // Verificar que el libro existe
            $book = $this->bookModel->getById($id);
            if (!$book) {
                AuthController::setFlashMessage('Libro no encontrado', 'error');
                return false;
            }

            // Eliminar el libro
            $result = $this->bookModel->delete($id);
            if ($result) {
                AuthController::setFlashMessage('Libro eliminado exitosamente', 'success');
                return true;
            } else {
                AuthController::setFlashMessage('Error al eliminar el libro', 'error');
                return false;
            }
        } catch (Exception $e) {
            error_log("Error en BookController::delete: " . $e->getMessage());
            AuthController::setFlashMessage('Error interno del servidor', 'error');
            return false;
        }
    }

    // Cambiar estado activo/inactivo
    public function toggleStatus($id) {
        try {
            $result = $this->bookModel->toggleStatus($id);
            if ($result) {
                AuthController::setFlashMessage('Estado del libro actualizado', 'success');
                return true;
            } else {
                AuthController::setFlashMessage('Error al cambiar el estado', 'error');
                return false;
            }
        } catch (Exception $e) {
            error_log("Error en BookController::toggleStatus: " . $e->getMessage());
            AuthController::setFlashMessage('Error interno del servidor', 'error');
            return false;
        }
    }

    // Cambiar estado destacado
    public function toggleFeatured($id) {
        try {
            $result = $this->bookModel->toggleFeatured($id);
            if ($result) {
                AuthController::setFlashMessage('Estado destacado actualizado', 'success');
                return true;
            } else {
                AuthController::setFlashMessage('Error al cambiar el estado destacado', 'error');
                return false;
            }
        } catch (Exception $e) {
            error_log("Error en BookController::toggleFeatured: " . $e->getMessage());
            AuthController::setFlashMessage('Error interno del servidor', 'error');
            return false;
        }
    }

    // Obtener detalles de un libro (para AJAX)
    public function getBookDetails($id) {
        try {
            $book = $this->bookModel->getById($id);
            
            if ($book) {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'success',
                    'book' => $book
                ]);
            } else {
                header('Content-Type: application/json');
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Libro no encontrado'
                ]);
            }
        } catch (Exception $e) {
            error_log("Error en BookController::getBookDetails: " . $e->getMessage());
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ]);
        }
        exit();
    }

    // Obtener libros para el frontend público
    public function getPublicBooks($limit = null, $featured = false) {
        try {
            if ($featured) {
                return $this->bookModel->getFeatured($limit);
            } else {
                return $this->bookModel->getActive($limit);
            }
        } catch (Exception $e) {
            error_log("Error en BookController::getPublicBooks: " . $e->getMessage());
            return [];
        }
    }

    // Buscar libros
    public function search($searchTerm, $limit = null) {
        try {
            return $this->bookModel->search($searchTerm, $limit);
        } catch (Exception $e) {
            error_log("Error en BookController::search: " . $e->getMessage());
            return [];
        }
    }

    // Obtener libros por categoría
    public function getByCategory($category, $limit = null) {
        try {
            return $this->bookModel->getByCategory($category, $limit);
        } catch (Exception $e) {
            error_log("Error en BookController::getByCategory: " . $e->getMessage());
            return [];
        }
    }

    // Validar datos del libro
    private function validateBookData($data) {
        $errors = [];

        // Título obligatorio
        if (empty(trim($data['title'] ?? ''))) {
            $errors[] = 'El título es obligatorio';
        }

        // Autor obligatorio
        if (empty(trim($data['author'] ?? ''))) {
            $errors[] = 'El autor es obligatorio';
        }

        // Precio válido
        $price = floatval($data['price'] ?? 0);
        if ($price <= 0) {
            $errors[] = 'El precio debe ser mayor a 0';
        }

        // URL de Amazon obligatoria y válida
        $amazonUrl = trim($data['amazon_url'] ?? '');
        if (empty($amazonUrl)) {
            $errors[] = 'La URL de Amazon es obligatoria';
        } elseif (!filter_var($amazonUrl, FILTER_VALIDATE_URL)) {
            $errors[] = 'La URL de Amazon no es válida';
        } elseif (!$this->isAmazonUrl($amazonUrl)) {
            $errors[] = 'La URL debe ser de Amazon';
        }

        // URL de imagen válida (si se proporciona)
        $coverImage = trim($data['cover_image'] ?? '');
        if (!empty($coverImage) && !filter_var($coverImage, FILTER_VALIDATE_URL)) {
            $errors[] = 'La URL de la portada no es válida';
        }

        // Fecha de publicación válida (si se proporciona)
        $publicationDate = $data['publication_date'] ?? '';
        if (!empty($publicationDate) && !$this->isValidDate($publicationDate)) {
            $errors[] = 'La fecha de publicación no es válida';
        }

        return $errors;
    }

    // Verificar si la URL es de Amazon
    private function isAmazonUrl($url) {
        $parsedUrl = parse_url($url);
        $host = $parsedUrl['host'] ?? '';
        
        return strpos($host, 'amazon.') !== false || 
               strpos($host, 'amzn.') !== false;
    }

    // Verificar si la fecha es válida
    private function isValidDate($date, $format = 'Y-m-d') {
        $d = \DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }

    // Obtener estadísticas de libros
    public function getStats() {
        try {
            return [
                'total_books' => $this->bookModel->count(),
                'active_books' => $this->bookModel->countActive(),
                'featured_books' => $this->bookModel->countFeatured(),
                'total_categories' => count($this->bookModel->getCategories())
            ];
        } catch (Exception $e) {
            error_log("Error en BookController::getStats: " . $e->getMessage());
            return [
                'total_books' => 0,
                'active_books' => 0,
                'featured_books' => 0,
                'total_categories' => 0
            ];
        }
    }

    // Obtener libros destacados
    public function getFeaturedBooks($limit = null) {
        try {
            return $this->bookModel->getFeatured($limit);
        } catch (Exception $e) {
            error_log("Error en BookController::getFeaturedBooks: " . $e->getMessage());
            return [];
        }
    }

    // Obtener libros activos
    public function getActiveBooks($limit = null) {
        try {
            return $this->bookModel->getActive($limit);
        } catch (Exception $e) {
            error_log("Error en BookController::getActiveBooks: " . $e->getMessage());
            return [];
        }
    }
}

// Si se accede directamente al archivo, manejar la solicitud
if (basename($_SERVER['PHP_SELF']) === 'BookController.php') {
    $controller = new BookController();
    $controller->handleRequest();
}
?>
