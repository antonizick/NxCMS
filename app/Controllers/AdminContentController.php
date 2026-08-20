<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Models\ActivityLog;
use App\Models\ContentPost;
use App\Models\Settings;
use App\Support\Uploads;

/**
 * "Post Content" — the unified News / YouTube / ProjectWork flow (spec's
 * single dropdown-driven form), plus the admin archive list (search/filter/
 * sort/paginate, same shape as the public ArchiveController but including
 * suppressed posts and an "all categories" option).
 */
final class AdminContentController extends AdminController
{
    private const PER_PAGE = 15;

    public function index(): void
    {
        $admin = $this->admin();

        $category = (string) ($_GET['category'] ?? 'all');
        if ($category !== 'all' && !in_array($category, ContentPost::CATEGORIES, true)) {
            $category = 'all';
        }

        $sort = (string) ($_GET['sort'] ?? 'newest');
        if (!array_key_exists($sort, ContentPost::SORTS)) {
            $sort = 'newest';
        }

        $q = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 120);

        $rotation = (string) ($_GET['rotation'] ?? 'all');
        if (!in_array($rotation, ContentPost::ROTATION_FILTERS, true)) {
            $rotation = 'all';
        }

        $total = ContentPost::searchAdminCount($category, $q, $rotation);
        $lastPage = max(1, (int) ceil($total / self::PER_PAGE));
        $page = max(1, min($lastPage, (int) ($_GET['page'] ?? 1)));

        $posts = ContentPost::searchAdmin($category, $q, $sort, self::PER_PAGE, ($page - 1) * self::PER_PAGE, $rotation);

        View::render('admin/content/index', [
            'pageTitle' => 'Post content — Admin',
            'admin' => $admin,
            'posts' => $posts,
            'category' => $category,
            'sort' => $sort,
            'q' => $q,
            'rotation' => $rotation,
            'rotationCounts' => ContentPost::rotationCounts(),
            'rotationLimit' => ContentPost::ROTATION_LIMIT,
            'page' => $page,
            'lastPage' => $lastPage,
            'total' => $total,
        ], 'layouts/admin');
    }

    public function new(): void
    {
        $admin = $this->admin();

        $this->form($admin, null, [
            'category' => 'news', 'title' => '', 'author' => Settings::site()['display_name'] ?? '', 'body' => '', 'link_url' => '',
            'image_url' => '', 'published_at' => date('Y-m-d\TH:i'), 'is_suppressed' => 0,
            'show_in_profile' => 0, 'show_in_map' => 0,
        ]);
    }

    public function edit(string $id): void
    {
        $admin = $this->admin();

        $post = ContentPost::findAny((int) $id);
        if ($post === null) {
            $this->redirect('/admin/content');
            return;
        }

        $post['published_at'] = str_replace(' ', 'T', substr((string) $post['published_at'], 0, 16));
        $this->form($admin, (int) $id, $post);
    }

    /**
     * Live preview fragment for the edit form's right-hand column — same
     * markup shape as article.php's focus-main content (see
     * admin/content/preview.php), rendered from whatever is currently in
     * the form rather than a saved row. Called on a debounced fetch() as
     * the admin types (see assets/js/app.js).
     */
    public function preview(): void
    {
        $this->admin();

        if (!$this->csrfOk()) {
            http_response_code(403);
            return;
        }

        $fields = [
            'category' => (string) ($_POST['category'] ?? ''),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'author' => trim((string) ($_POST['author'] ?? '')),
            'body' => (string) ($_POST['body'] ?? ''),
            'link_url' => trim((string) ($_POST['link_url'] ?? '')),
            'image_url' => (string) ($_POST['image_url'] ?? ''),
            'published_at' => (string) ($_POST['published_at'] ?? ''),
        ];

        header('Content-Type: text/html; charset=utf-8');
        echo View::partial('admin/content/preview', ['fields' => $fields]);
    }

    /**
     * Uploads a post image the moment it's chosen, decoupled from the post
     * row entirely (Uploads::store() just writes a content-hashed file and
     * hands back its URL) — the edit form's JS drops that URL into the
     * hidden image_url field, so attaching an image no longer needs "Save
     * post" (which redirects away to the archive list) just to happen.
     */
    public function uploadImage(): void
    {
        $this->admin();

        if (!$this->csrfOk()) {
            http_response_code(403);
            return;
        }

        header('Content-Type: text/plain; charset=utf-8');

        try {
            $url = Uploads::store($_FILES['image'] ?? null);
        } catch (\RuntimeException $e) {
            http_response_code(422);
            echo $e->getMessage();
            return;
        }

        if ($url === null) {
            http_response_code(422);
            echo 'No image selected.';
            return;
        }

        echo $url;
    }

    public function create(): void
    {
        $admin = $this->admin();
        [$fields, $error] = $this->validated(null);

        if (!$this->csrfOk()) {
            $this->form($admin, null, $_POST, 'Session expired — please try again.');
            return;
        }
        if ($error !== null) {
            $this->form($admin, null, $_POST, $error);
            return;
        }

        ContentPost::create($fields);
        ActivityLog::record((int) $admin['id'], 'content_created', $fields['title']);
        $this->redirect('/admin/content');
    }

    public function update(string $id): void
    {
        $admin = $this->admin();
        $postId = (int) $id;
        $existing = ContentPost::findAny($postId);
        if ($existing === null) {
            $this->redirect('/admin/content');
            return;
        }

        [$fields, $error] = $this->validated($existing['image_url']);

        if (!$this->csrfOk()) {
            $this->form($admin, $postId, $_POST, 'Session expired — please try again.');
            return;
        }
        if ($error !== null) {
            $this->form($admin, $postId, $_POST, $error);
            return;
        }

        if ($fields['image_url'] !== $existing['image_url']) {
            Uploads::delete($existing['image_url']);
        }

        ContentPost::update($postId, $fields);
        ActivityLog::record((int) $admin['id'], 'content_updated', $fields['title']);
        $this->redirect('/admin/content');
    }

    /**
     * Add or remove a post from one tile's carousel, straight from the list.
     * Driven by a checkbox that posts the state it now holds (see
     * ContentPost::setRotation), so curating a rotation doesn't mean opening
     * and saving every post in turn.
     */
    public function setRotation(string $id, string $tile): void
    {
        $admin = $this->admin();

        if ($this->csrfOk() && isset(ContentPost::ROTATIONS[$tile])) {
            $post = ContentPost::findAny((int) $id);
            if ($post !== null) {
                $on = !empty($_POST['on']);
                ContentPost::setRotation((int) $id, $tile, $on);
                ActivityLog::record(
                    (int) $admin['id'],
                    $on ? 'content_rotation_added' : 'content_rotation_removed',
                    $tile . ' — ' . $post['title']
                );
            }
        }

        $this->redirect($this->listUrl());
    }

    public function toggleSuppress(string $id): void
    {
        $admin = $this->admin();

        if ($this->csrfOk()) {
            $post = ContentPost::findAny((int) $id);
            ContentPost::toggleSuppress((int) $id);
            $becameSuppressed = $post !== null && !$post['is_suppressed'];
            ActivityLog::record(
                (int) $admin['id'],
                $becameSuppressed ? 'content_suppressed' : 'content_unsuppressed',
                $post['title'] ?? (string) $id
            );
        }
        $this->redirect($this->listUrl());
    }

    /**
     * The list URL rebuilt from the filter state the row's form carried, so
     * a row action lands back on the page it was taken from rather than
     * resetting to the top of an unfiltered list — which matters a lot once
     * the point of the screen is working through a filtered set.
     *
     * Rebuilt from validated values, never from the Referer header: this
     * ends up in a Location:, and a redirect target assembled from something
     * the client controls is how open redirects happen.
     */
    private function listUrl(): string
    {
        $category = (string) ($_POST['category'] ?? '');
        $rotation = (string) ($_POST['rotation'] ?? '');
        $sort = (string) ($_POST['sort'] ?? '');
        $page = (int) ($_POST['page'] ?? 1);

        $params = [
            'category' => in_array($category, ContentPost::CATEGORIES, true) ? $category : '',
            'rotation' => in_array($rotation, ContentPost::ROTATION_FILTERS, true) && $rotation !== 'all' ? $rotation : '',
            'sort' => array_key_exists($sort, ContentPost::SORTS) && $sort !== 'newest' ? $sort : '',
            'q' => mb_substr(trim((string) ($_POST['q'] ?? '')), 0, 120),
            'page' => $page > 1 ? (string) $page : '',
        ];
        $params = array_filter($params, static fn(string $v): bool => $v !== '');

        return '/admin/content' . ($params ? '?' . http_build_query($params) : '');
    }

    public function delete(string $id): void
    {
        $admin = $this->admin();

        if ($this->csrfOk()) {
            $post = ContentPost::findAny((int) $id);
            if ($post !== null) {
                Uploads::delete($post['image_url']);
                ContentPost::delete((int) $id);
                ActivityLog::record((int) $admin['id'], 'content_deleted', $post['title']);
            }
        }
        $this->redirect($this->listUrl());
    }

    /**
     * @param string|null $currentImageUrl the row's existing image, kept unless a new one is uploaded or "remove image" is checked
     * @return array{0: array<string, mixed>, 1: string|null}
     */
    private function validated(?string $currentImageUrl): array
    {
        $category = (string) ($_POST['category'] ?? '');
        $title = trim((string) ($_POST['title'] ?? ''));
        $author = trim((string) ($_POST['author'] ?? ''));
        if ($author === '') {
            $author = (string) (Settings::site()['display_name'] ?? '');
        }
        $body = trim((string) ($_POST['body'] ?? ''));
        $linkUrl = trim((string) ($_POST['link_url'] ?? ''));
        $publishedAt = (string) ($_POST['published_at'] ?? '');
        $isSuppressed = !empty($_POST['is_suppressed']) ? 1 : 0;
        // Home-page tile rotations (tile 1 profile, tile 7 map). Independent
        // of category — any post can be flagged into either or both.
        $showInProfile = !empty($_POST['show_in_profile']) ? 1 : 0;
        $showInMap = !empty($_POST['show_in_map']) ? 1 : 0;

        // The hidden image_url field (form.php) starts out holding whatever
        // $currentImageUrl already was, but the edit form's JS overwrites it
        // in place the moment an image finishes auto-uploading — so this is
        // the up-to-date value even though no file is riding along in
        // $_FILES on this particular submit.
        $postedImageUrl = trim((string) ($_POST['image_url'] ?? ''));
        $imageUrl = $postedImageUrl !== '' ? $postedImageUrl : $currentImageUrl;
        try {
            $uploaded = Uploads::store($_FILES['image'] ?? null);
            if ($uploaded !== null) {
                $imageUrl = $uploaded;
            }
            if (!empty($_POST['remove_image'])) {
                $imageUrl = null;
            }
        } catch (\RuntimeException $e) {
            return [
                ['category' => $category, 'title' => $title, 'author' => $author, 'body' => $body, 'link_url' => $linkUrl,
                    'image_url' => $imageUrl, 'published_at' => $publishedAt, 'is_suppressed' => $isSuppressed,
                    'show_in_profile' => $showInProfile, 'show_in_map' => $showInMap],
                $e->getMessage(),
            ];
        }

        $fields = [
            'category' => $category,
            'title' => $title,
            'author' => $author,
            'body' => $body,
            'link_url' => $linkUrl !== '' ? $linkUrl : null,
            'image_url' => $imageUrl,
            'published_at' => $this->parseDate($publishedAt),
            'is_suppressed' => $isSuppressed,
            'show_in_profile' => $showInProfile,
            'show_in_map' => $showInMap,
        ];

        if (!in_array($category, ContentPost::CATEGORIES, true)) {
            return [$fields, 'Choose a category.'];
        }
        if ($title === '' || mb_strlen($title) > 255) {
            return [$fields, 'Title is required (max 255 characters).'];
        }
        if (mb_strlen($author) > 128) {
            return [$fields, 'Author must be 128 characters or fewer.'];
        }
        if ($fields['published_at'] === null) {
            return [$fields, 'Enter a valid publish date.'];
        }

        return [$fields, null];
    }

    private function parseDate(string $value): ?string
    {
        if ($value === '') {
            return date('Y-m-d H:i:s');
        }

        $dt = \DateTimeImmutable::createFromFormat('Y-m-d\TH:i', $value);

        return $dt === false ? null : $dt->format('Y-m-d H:i:s');
    }

    /** @param array<string, mixed> $admin @param array<string, mixed> $fields */
    private function form(array $admin, ?int $id, array $fields, ?string $error = null): void
    {
        View::render('admin/content/form', [
            'pageTitle' => ($id === null ? 'Post content' : 'Edit post') . ' — Admin',
            'admin' => $admin,
            'id' => $id,
            'fields' => $fields,
            'error' => $error,
            'categories' => ContentPost::CATEGORIES,
            'wideShell' => true,
        ], 'layouts/admin');
    }
}
