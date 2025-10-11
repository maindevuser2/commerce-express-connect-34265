<?php
namespace Models;

use PDO;
use PDOException;

class Book {
    private $conn;
    private $table_name = "books";

    public function __construct($db) {
        $this->conn = $db;
    }

    // Crear un nuevo libro
    public function create($data) {
        try {
            $query = "INSERT INTO " . $this->table_name . " 
                     (title, author, description, price, amazon_url, cover_image, category, 
                      publication_date, is_featured, is_active, display_order, created_at) 
                     VALUES 
                     (:title, :author, :description, :price, :amazon_url, :cover_image, :category, 
                      :publication_date, :is_featured, :is_active, :display_order, NOW())";

            $stmt = $this->conn->prepare($query);

            // Sanitizar datos
            $data['title'] = htmlspecialchars(strip_tags($data['title']));
            $data['author'] = htmlspecialchars(strip_tags($data['author']));
            $data['description'] = htmlspecialchars(strip_tags($data['description']));
            $data['amazon_url'] = htmlspecialchars(strip_tags($data['amazon_url']));
            $data['cover_image'] = htmlspecialchars(strip_tags($data['cover_image']));
            $data['category'] = htmlspecialchars(strip_tags($data['category']));

            // Bind parameters
            $stmt->bindParam(':title', $data['title']);
            $stmt->bindParam(':author', $data['author']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':price', $data['price']);
            $stmt->bindParam(':amazon_url', $data['amazon_url']);
            $stmt->bindParam(':cover_image', $data['cover_image']);
            $stmt->bindParam(':category', $data['category']);
            $stmt->bindParam(':publication_date', $data['publication_date']);
            $stmt->bindParam(':is_featured', $data['is_featured']);
            $stmt->bindParam(':is_active', $data['is_active']);
            $stmt->bindParam(':display_order', $data['display_order']);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error creating book: " . $e->getMessage());
            return false;
        }
    }

    // Obtener todos los libros
    public function getAll($limit = null, $offset = null) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     ORDER BY display_order ASC, created_at DESC";
            
            if ($limit) {
                $query .= " LIMIT " . intval($limit);
                if ($offset) {
                    $query .= " OFFSET " . intval($offset);
                }
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting all books: " . $e->getMessage());
            return [];
        }
    }

    // Obtener libro por ID
    public function getById($id) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id LIMIT 1";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting book by ID: " . $e->getMessage());
            return false;
        }
    }

    // Actualizar libro
    public function update($id, $data) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET title = :title, author = :author, description = :description, 
                         price = :price, amazon_url = :amazon_url, cover_image = :cover_image, 
                         category = :category, publication_date = :publication_date, 
                         is_featured = :is_featured, is_active = :is_active, 
                         display_order = :display_order, updated_at = NOW()
                     WHERE id = :id";

            $stmt = $this->conn->prepare($query);

            // Sanitizar datos
            $data['title'] = htmlspecialchars(strip_tags($data['title']));
            $data['author'] = htmlspecialchars(strip_tags($data['author']));
            $data['description'] = htmlspecialchars(strip_tags($data['description']));
            $data['amazon_url'] = htmlspecialchars(strip_tags($data['amazon_url']));
            $data['cover_image'] = htmlspecialchars(strip_tags($data['cover_image']));
            $data['category'] = htmlspecialchars(strip_tags($data['category']));

            // Bind parameters
            $stmt->bindParam(':id', $id);
            $stmt->bindParam(':title', $data['title']);
            $stmt->bindParam(':author', $data['author']);
            $stmt->bindParam(':description', $data['description']);
            $stmt->bindParam(':price', $data['price']);
            $stmt->bindParam(':amazon_url', $data['amazon_url']);
            $stmt->bindParam(':cover_image', $data['cover_image']);
            $stmt->bindParam(':category', $data['category']);
            $stmt->bindParam(':publication_date', $data['publication_date']);
            $stmt->bindParam(':is_featured', $data['is_featured']);
            $stmt->bindParam(':is_active', $data['is_active']);
            $stmt->bindParam(':display_order', $data['display_order']);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error updating book: " . $e->getMessage());
            return false;
        }
    }

    // Eliminar libro
    public function delete($id) {
        try {
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error deleting book: " . $e->getMessage());
            return false;
        }
    }

    // Obtener libros activos
    public function getActive($limit = null, $offset = null) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE is_active = 1 
                     ORDER BY display_order ASC, created_at DESC";
            
            if ($limit) {
                $query .= " LIMIT " . intval($limit);
                if ($offset) {
                    $query .= " OFFSET " . intval($offset);
                }
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting active books: " . $e->getMessage());
            return [];
        }
    }

    // Obtener libros destacados - MÉTODO CORREGIDO
    public function getFeatured($limit = null) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE is_featured = 1 AND is_active = 1 
                     ORDER BY display_order ASC, created_at DESC";
            
            if ($limit) {
                $query .= " LIMIT " . intval($limit);
            }

            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting featured books: " . $e->getMessage());
            return [];
        }
    }

    // Alias para compatibilidad con el código existente
    public function readFeatured($limit = null) {
        return $this->getFeatured($limit);
    }

    // Obtener libros por categoría
    public function getByCategory($category, $limit = null, $offset = null) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE category = :category AND is_active = 1 
                     ORDER BY display_order ASC, created_at DESC";
            
            if ($limit) {
                $query .= " LIMIT " . intval($limit);
                if ($offset) {
                    $query .= " OFFSET " . intval($offset);
                }
            }

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':category', $category);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting books by category: " . $e->getMessage());
            return [];
        }
    }

    // Obtener categorías únicas
    public function getCategories() {
        try {
            $query = "SELECT DISTINCT category FROM " . $this->table_name . " 
                     WHERE category IS NOT NULL AND category != '' 
                     ORDER BY category ASC";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return $result ?: [];
        } catch (PDOException $e) {
            error_log("Error getting categories: " . $e->getMessage());
            return [];
        }
    }

    // Contar total de libros
    public function count() {
        try {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name;
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'];
        } catch (PDOException $e) {
            error_log("Error counting books: " . $e->getMessage());
            return 0;
        }
    }

    // Contar libros activos
    public function countActive() {
        try {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'];
        } catch (PDOException $e) {
            error_log("Error counting active books: " . $e->getMessage());
            return 0;
        }
    }

    // Contar libros destacados
    public function countFeatured() {
        try {
            $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " WHERE is_featured = 1 AND is_active = 1";
            $stmt = $this->conn->prepare($query);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['total'];
        } catch (PDOException $e) {
            error_log("Error counting featured books: " . $e->getMessage());
            return 0;
        }
    }

    // Buscar libros
    public function search($searchTerm, $limit = null, $offset = null) {
        try {
            $query = "SELECT * FROM " . $this->table_name . " 
                     WHERE (title LIKE :search OR author LIKE :search OR description LIKE :search) 
                     AND is_active = 1 
                     ORDER BY display_order ASC, created_at DESC";
            
            if ($limit) {
                $query .= " LIMIT " . intval($limit);
                if ($offset) {
                    $query .= " OFFSET " . intval($offset);
                }
            }

            $stmt = $this->conn->prepare($query);
            $searchParam = '%' . $searchTerm . '%';
            $stmt->bindParam(':search', $searchParam);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error searching books: " . $e->getMessage());
            return [];
        }
    }

    // Cambiar estado activo/inactivo
    public function toggleStatus($id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, 
                         updated_at = NOW() 
                     WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error toggling book status: " . $e->getMessage());
            return false;
        }
    }

    // Cambiar estado destacado
    public function toggleFeatured($id) {
        try {
            $query = "UPDATE " . $this->table_name . " 
                     SET is_featured = CASE WHEN is_featured = 1 THEN 0 ELSE 1 END, 
                         updated_at = NOW() 
                     WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':id', $id);
            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Error toggling book featured status: " . $e->getMessage());
            return false;
        }
    }
}
?>
