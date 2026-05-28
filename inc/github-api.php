<?php

function ypm_get_latest_package_info($owner, $repo, $token = '') {
    $api_url = "https://api.github.com/repos/$owner/$repo/releases/latest";
    $release_response = ypm_remote_get($api_url, $token);

    if ($release_response['success'] && isset($release_response['data']['zipball_url'])) {
        return [
            'success' => true,
            'http_code' => 200,
            'zip_url' => (string) $release_response['data']['zipball_url'],
            'version' => (string) (
                $release_response['data']['tag_name']
                ?? $release_response['data']['name']
                ?? ''
            ),
            'source' => 'release',
            'error' => '',
        ];
    }

    if ((int) $release_response['http_code'] === 404) {
        $tags_api = "https://api.github.com/repos/$owner/$repo/tags";
        $tags_response = ypm_remote_get($tags_api, $token);

        if (
            $tags_response['success']
            && is_array($tags_response['data'])
            && !empty($tags_response['data'])
            && isset($tags_response['data'][0]['zipball_url'])
        ) {
            return [
                'success' => true,
                'http_code' => 200,
                'zip_url' => (string) $tags_response['data'][0]['zipball_url'],
                'version' => (string) ($tags_response['data'][0]['name'] ?? ''),
                'source' => 'tag',
                'error' => '',
            ];
        }

        return [
            'success' => false,
            'http_code' => (int) $tags_response['http_code'],
            'zip_url' => '',
            'version' => '',
            'source' => 'tag',
            'error' => $tags_response['error'] ?: 'Could not fetch any release or tag from GitHub.',
        ];
    }

    return [
        'success' => false,
        'http_code' => (int) $release_response['http_code'],
        'zip_url' => '',
        'version' => '',
        'source' => 'release',
        'error' => $release_response['error'] ?: 'GitHub API error',
    ];
}

function ypm_get_release_by_tag($owner, $repo, $tag, $token = '') {
    $tag = trim((string) $tag);
    if ($tag === '') {
        return [
            'success' => false,
            'http_code' => 0,
            'zip_url' => '',
            'version' => '',
            'source' => 'release',
            'error' => 'Empty release tag.',
        ];
    }

    $api_url = "https://api.github.com/repos/$owner/$repo/releases/tags/" . rawurlencode($tag);
    $response = ypm_remote_get($api_url, $token);

    if ($response['success'] && isset($response['data']['zipball_url'])) {
        return [
            'success' => true,
            'http_code' => 200,
            'zip_url' => (string) $response['data']['zipball_url'],
            'version' => (string) ($response['data']['tag_name'] ?? $tag),
            'source' => 'release',
            'error' => '',
        ];
    }

    // Fall back to a tag with the same name
    if ((int) $response['http_code'] === 404) {
        $tag_zip = "https://api.github.com/repos/$owner/$repo/zipball/refs/tags/" . rawurlencode($tag);
        $verify = ypm_remote_get("https://api.github.com/repos/$owner/$repo/git/refs/tags/" . rawurlencode($tag), $token);
        if ($verify['success']) {
            return [
                'success' => true,
                'http_code' => 200,
                'zip_url' => $tag_zip,
                'version' => $tag,
                'source' => 'tag',
                'error' => '',
            ];
        }
    }

    return [
        'success' => false,
        'http_code' => (int) $response['http_code'],
        'zip_url' => '',
        'version' => '',
        'source' => 'release',
        'error' => $response['error'] ?: sprintf('Release "%s" not found.', $tag),
    ];
}

function ypm_get_branch_package_info($owner, $repo, $branch, $token = '') {
    $branch = trim((string) $branch);
    if ($branch === '') {
        return [
            'success' => false,
            'http_code' => 0,
            'zip_url' => '',
            'version' => '',
            'source' => 'branch',
            'error' => 'Empty branch name.',
        ];
    }

    $api_url = "https://api.github.com/repos/$owner/$repo/branches/" . rawurlencode($branch);
    $response = ypm_remote_get($api_url, $token);
    if (!$response['success']) {
        return [
            'success' => false,
            'http_code' => (int) $response['http_code'],
            'zip_url' => '',
            'version' => '',
            'source' => 'branch',
            'error' => $response['error'] ?: sprintf('Branch "%s" not found.', $branch),
        ];
    }

    $sha = '';
    if (isset($response['data']['commit']['sha'])) {
        $sha = substr((string) $response['data']['commit']['sha'], 0, 7);
    }

    return [
        'success' => true,
        'http_code' => 200,
        'zip_url' => "https://api.github.com/repos/$owner/$repo/zipball/" . rawurlencode($branch),
        'version' => $branch . ($sha !== '' ? '@' . $sha : ''),
        'source' => 'branch',
        'error' => '',
    ];
}

function ypm_get_default_branch_package_info($owner, $repo, $token = '') {
    $api_url = "https://api.github.com/repos/$owner/$repo";
    $response = ypm_remote_get($api_url, $token);
    if ($response['success'] && !empty($response['data']['default_branch'])) {
        return ypm_get_branch_package_info($owner, $repo, (string) $response['data']['default_branch'], $token);
    }

    foreach (['main', 'master'] as $candidate) {
        $branch_info = ypm_get_branch_package_info($owner, $repo, $candidate, $token);
        if ($branch_info['success']) {
            return $branch_info;
        }
    }

    return [
        'success' => false,
        'http_code' => (int) ($response['http_code'] ?? 0),
        'zip_url' => '',
        'version' => '',
        'source' => 'branch',
        'error' => $response['error'] ?: 'No default branch could be determined.',
    ];
}

function ypm_resolve_package_info($owner, $repo, $token = '', $branch = '', $version = '') {
    $branch = trim((string) $branch);
    $version = trim((string) $version);

    if ($version !== '') {
        return ypm_get_release_by_tag($owner, $repo, $version, $token);
    }

    if ($branch !== '') {
        return ypm_get_branch_package_info($owner, $repo, $branch, $token);
    }

    $latest = ypm_get_latest_package_info($owner, $repo, $token);
    if ($latest['success']) {
        return $latest;
    }

    $error_text = trim((string) ($latest['error'] ?? ''));
    $no_release_or_tag = (int) $latest['http_code'] === 200
        || stripos($error_text, 'Could not fetch any release or tag from GitHub.') !== false;

    if ($no_release_or_tag) {
        $fallback = ypm_get_default_branch_package_info($owner, $repo, $token);
        if ($fallback['success']) {
            return $fallback;
        }
    }

    return $latest;
}

function ypm_remote_get($url, $token = '') {
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT => 'YOURLS-Plugin-Manager',
        CURLOPT_HTTPHEADER => ypm_github_headers($token),
        CURLOPT_TIMEOUT => 10,
    ]);
    $res = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($res === false) {
        return [
            'success' => false,
            'http_code' => 0,
            'data' => null,
            'error' => $error ?: 'Unknown cURL error',
        ];
    }

    $decoded = json_decode($res, true);
    if ($http_code === 200) {
        return [
            'success' => true,
            'http_code' => $http_code,
            'data' => $decoded,
            'error' => '',
        ];
    }

    $api_error = '';
    if (is_array($decoded) && isset($decoded['message'])) {
        $api_error = (string) $decoded['message'];
    }
    if ($api_error === '') {
        $api_error = 'HTTP error from GitHub API';
    }

    return [
        'success' => false,
        'http_code' => $http_code,
        'data' => $decoded,
        'error' => $api_error,
    ];
}

function ypm_github_repository_exists($owner, $repo, $token = '') {
    $owner = trim((string) $owner);
    $repo = trim((string) $repo);
    if ($owner === '' || $repo === '') {
        return [
            'exists' => false,
            'http_code' => 0,
            'error' => 'Invalid repository coordinates',
        ];
    }

    $api_url = "https://api.github.com/repos/$owner/$repo";
    $response = ypm_remote_get($api_url, $token);

    return [
        'exists' => !empty($response['success']),
        'http_code' => (int) ($response['http_code'] ?? 0),
        'error' => (string) ($response['error'] ?? ''),
    ];
}

function download_url($url, $token = '') {
    $tmp = tempnam(sys_get_temp_dir(), 'ypm_');
    if ($tmp === false) {
        return false;
    }

    $fp = fopen($tmp, 'w+');
    if ($fp === false) {
        @unlink($tmp);
        return false;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_USERAGENT => 'YOURLS-Plugin-Manager',
        CURLOPT_FAILONERROR => true,
        CURLOPT_HTTPHEADER => ypm_github_headers($token),
    ]);
    $exec = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    fclose($fp);

    if (!$exec || $http_code < 200 || $http_code >= 300 || filesize($tmp) === 0) {
        @unlink($tmp);
        return false;
    }

    return $tmp;
}

function ypm_github_headers($token = '') {
    $headers = [
        'Accept: application/vnd.github+json',
        'X-GitHub-Api-Version: 2022-11-28',
    ];
    if ($token !== '') {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    return $headers;
}
