<?php
declare(strict_types=1);

require_once __DIR__ . '/../auth/helpers.php';
require_once __DIR__ . '/../includes/plan-rules.php';

function public_reviews(PDO $pdo, int $businessId): void
{
    $stmt = $pdo->prepare(
        "SELECT nombre_publico, calificacion, comentario, creado_en
         FROM resenas
         WHERE negocio_id = :negocio_id
           AND estado = 'aprobada'
         ORDER BY creado_en DESC
         LIMIT 30"
    );
    $stmt->execute(['negocio_id' => $businessId]);

    json_response(['ok' => true, 'reviews' => $stmt->fetchAll()]);
}

$pdo = db();
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $businessId = (int) ($_GET['negocio_id'] ?? 0);

    if ($businessId <= 0) {
        json_response(['ok' => false, 'message' => 'Negocio invalido.'], 422);
    }

    public_reviews($pdo, $businessId);
}

if ($method !== 'POST') {
    json_response(['ok' => false, 'message' => 'Metodo no permitido.'], 405);
}

$businessId = (int) post_value('negocio_id');
$name = post_value('nombre');
$email = strtolower(post_value('email'));
$rating = (int) post_value('calificacion');
$comment = post_value('comentario');

if ($businessId <= 0 || $name === '' || $rating < 1 || $rating > 5 || $comment === '') {
    json_response(['ok' => false, 'message' => 'Completa la resena correctamente.'], 422);
}

if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'message' => 'Ingresa un correo valido.'], 422);
}

try {
    $stmt = $pdo->prepare('SELECT id FROM negocios WHERE id = :id AND estado = "activo" LIMIT 1');
    $stmt->execute(['id' => $businessId]);

    if (!$stmt->fetch()) {
        json_response(['ok' => false, 'message' => 'No encontramos el negocio.'], 404);
    }

    $plan = get_business_plan($pdo, $businessId);

    if (!plan_allows($plan, 'resenas')) {
        json_response([
            'ok' => false,
            'message' => 'Las resenas publicas son una funcion Premium de este negocio.',
            'upgrade_required' => true,
        ], 403);
    }

    start_app_session();
    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

    $stmt = $pdo->prepare(
        "INSERT INTO resenas (
            negocio_id,
            usuario_id,
            nombre_publico,
            email,
            calificacion,
            comentario,
            estado
         )
         VALUES (
            :negocio_id,
            :usuario_id,
            :nombre_publico,
            :email,
            :calificacion,
            :comentario,
            'pendiente'
         )"
    );
    $stmt->execute([
        'negocio_id' => $businessId,
        'usuario_id' => $userId,
        'nombre_publico' => $name,
        'email' => $email !== '' ? $email : null,
        'calificacion' => $rating,
        'comentario' => $comment,
    ]);

    json_response([
        'ok' => true,
        'message' => 'Gracias. Tu resena quedo pendiente de revision.',
    ]);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'No pudimos guardar la resena.'], 500);
}
