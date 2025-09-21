<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

// Obtener parámetros
$video_id = $_GET['video_id'] ?? $_GET['id'] ?? '';
$playlist_id = $_GET['playlist_id'] ?? '';

if (empty($video_id)) {
    header('Location: home.php');
    exit();
}

// Incluir dependencias
require_once __DIR__ . '/../../config/Database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Video.php';
require_once __DIR__ . '/../../models/Playlist.php';
require_once __DIR__ . '/../../models/VideoFile.php';
require_once __DIR__ . '/../../models/UserCourse.php';

use Models\User;
use Models\Video;
use Models\Playlist;
use Models\VideoFile;
use Models\UserCourse;

$database = new \Database();
$db = $database->getConnection();

$userModel = new User($db);
$videoModel = new Video($db);
$playlistModel = new Playlist($db);
$videoFileModel = new VideoFile($db);
$userCourseModel = new UserCourse($db);

$user_id = $_SESSION['user_id'];

// Obtener información del video
$video = $videoModel->readOne($video_id);
if (!$video) {
    header('Location: home.php?error=video_not_found');
    exit();
}

// Si no se proporcionó playlist_id, obtenerlo del video
if (empty($playlist_id)) {
    $playlist_id = $video['playlist_id'];
}

// Verificar acceso al curso
if (!$userCourseModel->hasAccess($user_id, $playlist_id)) {
    header('Location: home.php?error=no_access');
    exit();
}

// Obtener información del curso
$playlist = $playlistModel->readOne($playlist_id);
if (!$playlist) {
    header('Location: home.php?error=course_not_found');
    exit();
}

// Obtener todos los videos del curso
$allVideos = $videoModel->readByPlaylist($playlist_id);

// Obtener archivos descargables del video
$videoFiles = $videoFileModel->readByVideo($video_id);

// Encontrar video anterior y siguiente
$currentIndex = -1;
for ($i = 0; $i < count($allVideos); $i++) {
    if ($allVideos[$i]['id'] == $video_id) {
        $currentIndex = $i;
        break;
    }
}

$previousVideo = ($currentIndex > 0) ? $allVideos[$currentIndex - 1] : null;
$nextVideo = ($currentIndex < count($allVideos) - 1) ? $allVideos[$currentIndex + 1] : null;

// Obtener otros videos del curso (excluyendo el actual)
$related_videos = array_filter($allVideos, function($v) use ($video_id) {
    return $v['id'] != $video_id;
});

$pageTitle = $video['title'];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="../../public/css/client/view-video.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
</head>
<body>
    <div class="video-container">
        <a href="playlist_videos.php?id=<?php echo $playlist_id; ?>" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Volver al Curso
        </a>

        <!-- Notificación de descarga -->
        <div class="download-notification" id="downloadNotification">
            <i class="fas fa-check-circle"></i>
            <span>¡Descarga iniciada exitosamente!</span>
        </div>
        
        <div class="main-content">
            <div class="video-section">
                <div class="video-player-container">
                    <video class="video-player" controls>
                        <source src="../../<?php echo htmlspecialchars($video['file_path']); ?>" type="video/mp4">
                        Tu navegador no soporta la reproducción de video.
                    </video>
                </div>

                <div class="video-info">
                    <h1 class="video-title"><?php echo htmlspecialchars($video['title']); ?></h1>

                    <div class="course-info">
                        <span>Curso: <a href="playlist_videos.php?id=<?php echo $playlist_id; ?>"><?php echo htmlspecialchars($playlist['title'] ?? $playlist['name']); ?></a></span>
                        <?php if (isset($playlist['level']) && $playlist['level']): ?>
                            <span class="course-badge"><?php echo htmlspecialchars($playlist['level']); ?></span>
                        <?php endif; ?>

                        <?php if (!empty($videoFiles)): ?>
                            <span><i class="fas fa-download"></i> <?php echo count($videoFiles); ?> archivo<?php echo count($videoFiles) > 1 ? 's' : ''; ?> disponible<?php echo count($videoFiles) > 1 ? 's' : ''; ?></span>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($video['description'])): ?>
                        <div class="video-description">
                            <?php echo nl2br(htmlspecialchars($video['description'])); ?>
                        </div>
                    <?php endif; ?>

                    <?php if (!empty($videoFiles)): ?>
                        <div class="downloadable-files">
                            <div class="files-header">
                                <i class="fas fa-download"></i>
                                <span>Archivos Descargables</span>
                            </div>
                            <div class="files-list">
                                <?php foreach ($videoFiles as $file): ?>
                                    <div class="file-item">
                                        <div class="file-icon <?php echo getFileIconClass($file['file_type']); ?>">
                                            <i class="<?php echo getFileIcon($file['file_type']); ?>"></i>
                                        </div>
                                        <div class="file-info">
                                            <div class="file-name"><?php echo htmlspecialchars($file['original_name']); ?></div>
                                            <div class="file-meta">
                                                <span class="file-size"><?php echo formatFileSize($file['file_size']); ?></span>
                                                <span><i class="fas fa-calendar-alt"></i> <?php echo date('d/m/Y', strtotime($file['created_at'])); ?></span>
                                                <?php if (!empty($file['description'])): ?>
                                                    <span><i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($file['description']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <a href="download.php?id=<?php echo $file['id']; ?>&video_id=<?php echo $video_id; ?>&user_id=<?php echo $user_id; ?>" 
                                           class="download-btn" 
                                           onclick="showDownloadNotification()"
                                           target="_blank">
                                            <i class="fas fa-download"></i>
                                            Descargar
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="downloadable-files">
                            <div class="no-files">
                                <i class="fas fa-folder-open"></i>
                                <p>No hay archivos descargables para este video</p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="sidebar">
                <?php if (!empty($related_videos)): ?>
                    <div class="course-progress">
                        <div class="progress-header">
                            <i class="fas fa-chart-line"></i>
                            Progreso del Curso
                        </div>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?php echo round((1 / (count($related_videos) + 1)) * 100); ?>%"></div>
                        </div>
                        <div class="progress-text">
                            1 de <?php echo count($related_videos) + 1; ?> videos completados
                        </div>
                    </div>
                <?php endif; ?>

                <?php if (!empty($related_videos)): ?>
                    <div class="related-videos">
                        <h3>
                            <i class="fas fa-play-circle"></i>
                            Más videos del curso
                        </h3>
                        <?php foreach ($related_videos as $index => $related_video): ?>
                            <a href="view_video.php?video_id=<?php echo $related_video['id']; ?>&playlist_id=<?php echo $playlist_id; ?>" class="related-video-item" data-video-id="<?php echo $related_video['id']; ?>">
                                <div class="related-video-thumb">
                                    <?php if (!empty($related_video['thumbnail_image'])): ?>
                                        <img src="../../<?php echo htmlspecialchars($related_video['thumbnail_image']); ?>" alt="<?php echo htmlspecialchars($related_video['title']); ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="no-thumbnail">
                                            <i class="fas fa-video"></i><br>
                                            <small>Sin miniatura</small>
                                        </div>
                                    <?php endif; ?>
                                    <div class="play-overlay">
                                        <i class="fas fa-play"></i>
                                    </div>
                                </div>
                                <div class="related-video-info">
                                    <div class="related-video-title"><?php echo htmlspecialchars($related_video['title']); ?></div>
                                    <?php if (!empty($related_video['description'])): ?>
                                        <div class="related-video-desc">
                                            <?php 
                                            $desc = strip_tags($related_video['description']);
                                            echo htmlspecialchars(strlen($desc) > 100 ? substr($desc, 0, 100) . '...' : $desc); 
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="related-videos">
                        <h3>
                            <i class="fas fa-info-circle"></i>
                            Videos del curso
                        </h3>
                        <div style="text-align: center; padding: 2rem; color: #718096;">
                            <i class="fas fa-video" style="font-size: 2rem; margin-bottom: 1rem; opacity: 0.5;"></i>
                            <p>Este es el único video disponible en este curso por el momento.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="public/js/file-download.js"></script>
</body>
</html>

<?php
function getFileIconClass($fileType) {
    $extension = strtolower(pathinfo($fileType, PATHINFO_EXTENSION));
    
    if ($fileType === 'application/pdf' || $extension === 'pdf') return 'pdf';
    if (strpos($fileType, 'image/') === 0 || in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) return 'image';
    if (strpos($fileType, 'word') !== false || in_array($extension, ['doc', 'docx'])) return 'word';
    if (strpos($fileType, 'excel') !== false || strpos($fileType, 'sheet') !== false || in_array($extension, ['xls', 'xlsx'])) return 'excel';
    if (strpos($fileType, 'powerpoint') !== false || strpos($fileType, 'presentation') !== false || in_array($extension, ['ppt', 'pptx'])) return 'powerpoint';
    if ($fileType === 'text/plain' || $extension === 'txt') return 'text';
    if (strpos($fileType, 'zip') !== false || strpos($fileType, 'rar') !== false || in_array($extension, ['zip', 'rar', '7z'])) return 'archive';
    return 'default';
}

function getFileIcon($fileType) {
    $extension = strtolower(pathinfo($fileType, PATHINFO_EXTENSION));
    
    $icons = [
        'pdf' => 'fas fa-file-pdf',
        'jpg' => 'fas fa-file-image',
        'jpeg' => 'fas fa-file-image',
        'png' => 'fas fa-file-image',
        'gif' => 'fas fa-file-image',
        'webp' => 'fas fa-file-image',
        'doc' => 'fas fa-file-word',
        'docx' => 'fas fa-file-word',
        'xls' => 'fas fa-file-excel',
        'xlsx' => 'fas fa-file-excel',
        'ppt' => 'fas fa-file-powerpoint',
        'pptx' => 'fas fa-file-powerpoint',
        'txt' => 'fas fa-file-alt',
        'zip' => 'fas fa-file-archive',
        'rar' => 'fas fa-file-archive',
        '7z' => 'fas fa-file-archive'
    ];

    // Verificar por extensión primero
    if (isset($icons[$extension])) {
        return $icons[$extension];
    }

    // Verificar por tipo MIME
    if ($fileType === 'application/pdf' || strpos($fileType, 'pdf') !== false) return 'fas fa-file-pdf';
    if (strpos($fileType, 'image/') === 0) return 'fas fa-file-image';
    if (strpos($fileType, 'word') !== false) return 'fas fa-file-word';
    if (strpos($fileType, 'excel') !== false || strpos($fileType, 'sheet') !== false) return 'fas fa-file-excel';
    if (strpos($fileType, 'powerpoint') !== false || strpos($fileType, 'presentation') !== false) return 'fas fa-file-powerpoint';
    if ($fileType === 'text/plain') return 'fas fa-file-alt';
    if (strpos($fileType, 'zip') !== false || strpos($fileType, 'rar') !== false) return 'fas fa-file-archive';
    
    return 'fas fa-file';
}

function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>
