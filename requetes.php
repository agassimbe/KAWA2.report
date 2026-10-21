<?php

/**
 * requetes.php
 *
 * Toutes les requêtes SQL du dashboard.
 *
 * La connexion PDO $pdo est fournie
 * par database.php
 *
 * Toutes les fonctions acceptent en option
 * $dateDebut / $dateFin (format 'YYYY-MM-DD')
 * pour filtrer sur date_expedition.
 */


/* =========================================================
   HELPER : filtre de dates commun
   =========================================================
   Construit le fragment SQL "AND date_expedition >= :date_debut"
   etc. et les paramètres associés, pour éviter de dupliquer
   cette logique dans chaque fonction.
   ========================================================= */

function buildDateFilter(
    ?string $dateDebut,
    ?string $dateFin,
    string $colonne = 'date_expedition'
): array {

    $sql = '';
    $params = [];

    if ($dateDebut !== null && $dateDebut !== '') {
        $sql .= " AND {$colonne} >= :date_debut ";
        $params['date_debut'] = $dateDebut;
    }

    if ($dateFin !== null && $dateFin !== '') {
        $sql .= " AND {$colonne} <= :date_fin ";
        $params['date_fin'] = $dateFin;
    }

    return [$sql, $params];
}


/* =========================================================
   KPI GLOBAUX
   ========================================================= */


/**
 * Nombre total de dessertes
 */
function getTotalDessertes(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): int {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT COUNT(*) AS total
        FROM dessertes
        WHERE 1 = 1
        {$filtreSql}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}


/**
 * Nombre de tournées
 */
function getTotalTournees(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): int {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT COUNT(DISTINCT numero_tournee) AS total
        FROM dessertes
        WHERE numero_tournee IS NOT NULL
        {$filtreSql}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}


/**
 * Nombre de clients
 */
function getTotalClients(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): int {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT COUNT(DISTINCT client) AS total
        FROM dessertes
        WHERE client IS NOT NULL
          AND client <> ''
        {$filtreSql}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
}


/**
 * Montant total transporté
 */
function getMontantTotal(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): float {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT COALESCE(SUM(montant_colis), 0) AS total
        FROM dessertes
        WHERE 1 = 1
        {$filtreSql}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (float) $stmt->fetchColumn();
}


/**
 * Kilométrage total
 */
function getKilometrageTotal(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): float {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT COALESCE(
            SUM(kilometrage_arrivee - kilometrage_depart),
            0
        ) AS total
        FROM dessertes
        WHERE kilometrage_arrivee IS NOT NULL
          AND kilometrage_depart IS NOT NULL
        {$filtreSql}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (float) $stmt->fetchColumn();
}


/* =========================================================
   ETAT DES DESSERTES
   ========================================================= */


/**
 * Répartition par état
 */
function getDessertesParEtat(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): array {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT
            etat_desserte,
            COUNT(*) AS total
        FROM dessertes
        WHERE 1 = 1
        {$filtreSql}
        GROUP BY etat_desserte
        ORDER BY total DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


/* =========================================================
   DESSERTES PAR CENTRE
   ========================================================= */


/**
 * Nombre de dessertes par centre
 */
function getDessertesParCentre(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): array {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT
            centre,
            COUNT(*) AS total
        FROM dessertes
        WHERE centre IS NOT NULL
        {$filtreSql}
        GROUP BY centre
        ORDER BY total DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


/* =========================================================
   DESSERTES PAR CLIENT
   ========================================================= */


/**
 * Nombre de dessertes par client
 */
function getDessertesParClient(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): array {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT
            client,
            COUNT(*) AS total
        FROM dessertes
        WHERE client IS NOT NULL
        {$filtreSql}
        GROUP BY client
        ORDER BY total DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


/* =========================================================
   MONTANT PAR CLIENT
   ========================================================= */


/**
 * Montant total par client
 */
function getMontantParClient(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): array {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT
            client,
            COALESCE(SUM(montant_colis), 0) AS montant_total
        FROM dessertes
        WHERE client IS NOT NULL
        {$filtreSql}
        GROUP BY client
        ORDER BY montant_total DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


/* =========================================================
   EVOLUTION JOURNALIERE
   ========================================================= */


/**
 * Nombre de dessertes par jour
 */
function getDessertesParJour(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): array {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT
            date_expedition AS date,
            COUNT(*) AS total
        FROM dessertes
        WHERE date_expedition IS NOT NULL
        {$filtreSql}
        GROUP BY date_expedition
        ORDER BY date_expedition
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


/* =========================================================
   MONTANT PAR JOUR
   ========================================================= */


/**
 * Evolution du montant transporté
 */
function getMontantParJour(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): array {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT
            date_expedition AS date,
            COALESCE(SUM(montant_colis), 0) AS montant
        FROM dessertes
        WHERE date_expedition IS NOT NULL
        {$filtreSql}
        GROUP BY date_expedition
        ORDER BY date_expedition
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


/* =========================================================
   TYPES DE DESSERTE
   ========================================================= */


/**
 * Répartition des types
 */
function getDessertesParType(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): array {

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT
            type_desserte,
            COUNT(*) AS total
        FROM dessertes
        WHERE type_desserte IS NOT NULL
        {$filtreSql}
        GROUP BY type_desserte
        ORDER BY total DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


/* =========================================================
   TOP CLIENTS
   ========================================================= */


/**
 * Top clients
 */
function getTopClients(
    PDO $pdo,
    int $limit = 10,
    ?string $dateDebut = null,
    ?string $dateFin = null
): array {

    $limit = max(1, min($limit, 100));

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT
            client,
            COUNT(*) AS nombre_dessertes,
            COALESCE(SUM(montant_colis), 0) AS montant_total
        FROM dessertes
        WHERE client IS NOT NULL
        {$filtreSql}
        GROUP BY client
        ORDER BY montant_total DESC
        LIMIT {$limit}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


/* =========================================================
   TOP CENTRES
   ========================================================= */


/**
 * Top centres
 */
function getTopCentres(
    PDO $pdo,
    int $limit = 10,
    ?string $dateDebut = null,
    ?string $dateFin = null
): array {

    $limit = max(1, min($limit, 100));

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT
            centre,
            COUNT(*) AS nombre_dessertes,
            COALESCE(SUM(montant_colis), 0) AS montant_total
        FROM dessertes
        WHERE centre IS NOT NULL
        {$filtreSql}
        GROUP BY centre
        ORDER BY nombre_dessertes DESC
        LIMIT {$limit}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


/* =========================================================
   DERNIERES DESSERTES
   ========================================================= */


/**
 * Dernières dessertes
 *
 * NB : le tri reste sur date_import (ordre d'arrivée en base),
 * mais le filtre s'applique sur date_expedition pour rester
 * cohérent avec le reste du dashboard.
 */
function getDernieresDessertes(
    PDO $pdo,
    int $limit = 10,
    ?string $dateDebut = null,
    ?string $dateFin = null
): array {

    $limit = max(1, min($limit, 100));

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    $sql = "
        SELECT
            numero_desserte,
            numero_tournee,
            centre,
            client,
            type_desserte,
            date_expedition,
            date_arrivee,
            montant_colis,
            etat_desserte
        FROM dessertes
        WHERE 1 = 1
        {$filtreSql}
        ORDER BY date_import DESC
        LIMIT {$limit}
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll();
}


/* =========================================================
   PERFORMANCE / DELAI
   ========================================================= */


/**
 * Durée moyenne d'une desserte
 *
 * Compatible MySQL et PostgreSQL
 * grâce à une adaptation du SQL.
 */
function getDureeMoyenneDessertes(
    PDO $pdo,
    ?string $dateDebut = null,
    ?string $dateFin = null
): ?float {

    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    [$filtreSql, $params] = buildDateFilter($dateDebut, $dateFin);

    if ($driver === 'mysql') {

        $sql = "
            SELECT
                AVG(
                    TIMESTAMPDIFF(
                        MINUTE,
                        TIMESTAMP(date_expedition, heure_expedition),
                        TIMESTAMP(date_arrivee, heure_arrivee)
                    )
                ) AS duree_moyenne
            FROM dessertes
            WHERE date_expedition IS NOT NULL
              AND heure_expedition IS NOT NULL
              AND date_arrivee IS NOT NULL
              AND heure_arrivee IS NOT NULL
            {$filtreSql}
        ";

    } else {

        $sql = "
            SELECT
                AVG(
                    EXTRACT(
                        EPOCH FROM (
                            (date_arrivee + heure_arrivee)
                            -
                            (date_expedition + heure_expedition)
                        )
                    ) / 60
                ) AS duree_moyenne
            FROM dessertes
            WHERE date_expedition IS NOT NULL
              AND heure_expedition IS NOT NULL
              AND date_arrivee IS NOT NULL
              AND heure_arrivee IS NOT NULL
            {$filtreSql}
        ";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $result = $stmt->fetchColumn();

    return $result !== false
        ? round((float) $result, 2)
        : null;
}
