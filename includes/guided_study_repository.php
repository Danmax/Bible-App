<?php

declare(strict_types=1);

function guided_studies_available(): bool
{
    static $available = null;

    if ($available !== null) {
        return $available;
    }

    try {
        $statement = db()->query("SHOW TABLES LIKE 'guided_studies'");
        $available = $statement->fetch() !== false;
    } catch (Throwable $exception) {
        $available = false;
    }

    return $available;
}

function fetch_guided_study(int $studyId, int $userId): ?array
{
    $statement = db()->prepare(
        'SELECT * FROM guided_studies WHERE id = :id AND user_id = :user_id LIMIT 1'
    );
    $statement->execute(['id' => $studyId, 'user_id' => $userId]);
    $study = $statement->fetch();

    return $study ?: null;
}

function fetch_guided_studies(int $userId, int $limit = 12): array
{
    $statement = db()->prepare(
        'SELECT guided_studies.*, books.name AS book_name
        FROM guided_studies
        INNER JOIN books ON books.id = guided_studies.book_id
        WHERE guided_studies.user_id = :user_id
        ORDER BY guided_studies.updated_at DESC
        LIMIT :limit'
    );
    $statement->bindValue('user_id', $userId, PDO::PARAM_INT);
    $statement->bindValue('limit', max(1, $limit), PDO::PARAM_INT);
    $statement->execute();

    return $statement->fetchAll();
}

function create_guided_study(int $userId, array $study): int
{
    $statement = db()->prepare(
        'INSERT INTO guided_studies
            (user_id, book_id, chapter_number, start_verse, end_verse, translation, observation, interpretation, application, prayer)
        VALUES
            (:user_id, :book_id, :chapter_number, :start_verse, :end_verse, :translation, :observation, :interpretation, :application, :prayer)'
    );
    $statement->execute(guided_study_parameters($userId, $study));

    return (int) db()->lastInsertId();
}

function update_guided_study(int $studyId, int $userId, array $study): void
{
    $parameters = guided_study_parameters($userId, $study);
    $parameters['id'] = $studyId;

    $statement = db()->prepare(
        'UPDATE guided_studies
        SET book_id = :book_id, chapter_number = :chapter_number, start_verse = :start_verse,
            end_verse = :end_verse, translation = :translation, observation = :observation,
            interpretation = :interpretation, application = :application, prayer = :prayer
        WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute($parameters);
}

function guided_study_parameters(int $userId, array $study): array
{
    return [
        'user_id' => $userId,
        'book_id' => (int) $study['book_id'],
        'chapter_number' => (int) $study['chapter_number'],
        'start_verse' => (int) $study['start_verse'],
        'end_verse' => (int) $study['end_verse'],
        'translation' => strtoupper(trim((string) $study['translation'])),
        'observation' => trim((string) $study['observation']),
        'interpretation' => trim((string) $study['interpretation']),
        'application' => trim((string) $study['application']),
        'prayer' => trim((string) $study['prayer']),
    ];
}
