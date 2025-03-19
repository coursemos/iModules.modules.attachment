<?php
/**
 * 이 파일은 아이모듈 첨부파일모듈의 일부입니다. (https://www.imodules.io)
 *
 * 첨부파일 클래스를 정의한다.
 * 첨부된 파일데이터를 관리하고, 첨부파일 업로드가 필요한 곳에 파일업로더를 제공한다.
 *
 * @file /modules/attachment/Attachment.php
 * @author sungjin <esung246@naddle.net>
 * @license MIT License
 * @modified 2025. 3. 19.
 */
namespace modules\attachment;
class Attachment extends \Module
{
    /**
     * @var \modules\attachment\dtos\File[] $_files 파일정보를 보관한다.
     */
    private static array $_files = [];

    /**
     * @var \modules\attachment\dtos\Attachment[] $_attachments 첨부파일정보를 보관한다.
     */
    private static array $_attachments = [];

    /**
     * 모듈을 설정을 초기화한다.
     */
    public function init(): void
    {
        /**
         * 모듈 라우터를 초기화한다.
         */
        \Router::add('/(files|drafts|attachments)/{type}/{file_id}/{name}', '#', 'blob', [$this, 'doRoute']);
    }

    /**
     * 업로더 클래스를 가져온다.
     *
     * @param string $id 업로더 고유값 (NULL 인 경우 신규로 생성하고, 값이 존재하는 경우 기존에 첨부된 파일을 가져온다.)
     * @return \modules\attachment\Uploader $uploader
     */
    public function getUploader(?string $id = null): \modules\attachment\Uploader
    {
        $uploader = new \modules\attachment\Uploader($id);
        return $uploader;
    }

    /**
     * 첨부파일 임시저장폴더를 가져온다.
     *
     * @return string $dir 폴더
     */
    public function getDraftDir(): string
    {
        $dir = 'drafts';
        if (\File::createDirectory(\Configs::attachment() . '/' . $dir) === false) {
            \ErrorHandler::print($this->error('NOT_WRITABLE'));
        }

        return $dir;
    }

    /**
     * 첨부파일 임시저장폴더를 가져온다.
     *
     * @return string $dir 폴더
     */
    public function getDraftPath(): string
    {
        return \Configs::attachment() . '/' . $this->getDraftDir();
    }

    /**
     * 임시폴더를 가져온다.
     *
     * @return string $dir 폴더
     */
    public function getTempPath(): string
    {
        $path = \Configs::attachment() . '/temp';
        if (is_dir($path) == true || \File::createDirectory($path) == true) {
            return $path;
        }

        return $this->getDraftPath();
    }

    /**
     * 파일해시에 따른 파일이 저장된 폴더를 가져온다.
     *
     * @param string $hash 파일해시
     * @return string $dir 폴더
     */
    public function getFileDir(string $hash): string
    {
        $dir = 'files/' . substr($hash, 0, 1) . '/' . substr($hash, 1, 1);
        if (\File::createDirectory(\Configs::attachment() . '/' . $dir) === false) {
            \ErrorHandler::print($this->error('NOT_WRITABLE'));
        }

        return $dir;
    }

    /**
     * 첨부파일이 존재하는지 확인한다.
     *
     * @param int $file_id 첨부파일 고유값
     * @return bool $has_file
     */
    public function hasFile(int $file_id): bool
    {
        return $this->getFile($file_id) !== null;
    }

    /**
     * 첨부파일 정보를 가져온다.
     *
     * @param string $hash 파일해시
     * @return ?\modules\attachment\dtos\File $file
     */
    public function getFile(string $hash): ?\modules\attachment\dtos\File
    {
        if (isset(self::$_files[$hash]) == false) {
            $file = $this->db()
                ->select()
                ->from($this->table('files'))
                ->where('hash', $hash)
                ->getOne();

            if ($file === null) {
                return null;
            }

            self::$_files[$hash] = new \modules\attachment\dtos\File($file);
        }

        return self::$_files[$hash];
    }

    /**
     * 첨부파일 정보를 가져온다.
     *
     * @param string $attachment_id 첨부파일고유값 또는 임시파일 고유값
     * @return ?\modules\attachment\dtos\Attachment $attachment
     */
    public function getAttachment(string $attachment_id): ?\modules\attachment\dtos\Attachment
    {
        if (isset(self::$_attachments[$attachment_id]) == false) {
            /**
             * 첨부파일 목록 또는 임시파일 목록에서 정보를 가져온다.
             */
            $attachment =
                $this->db()
                    ->select()
                    ->from($this->table('attachments'), 'a')
                    ->join($this->table('files'), 'f', 'a.hash=f.hash')
                    ->where('a.attachment_id', $attachment_id)
                    ->getOne() ??
                $this->db()
                    ->select()
                    ->from($this->table('drafts'))
                    ->where('draft_id', $attachment_id)
                    ->getOne();

            if ($attachment === null) {
                return null;
            }

            self::$_attachments[$attachment_id] = new \modules\attachment\dtos\Attachment($attachment);
        }

        return self::$_attachments[$attachment_id];
    }

    /**
     * 특정위치에 첨부된 첨부파일목록을 가져온다.
     *
     * @param \Component $component 이동할 컴포넌트객체
     * @param string $position_type 이동할 업로드위치종류
     * @param string|int $position_id 이동할 업로드위치고유값
     * @return \modules\attachment\dtos\Attachment[] $attachments 첨부파일목록
     */
    public function getAttachments(\Component $component, string $position_type, string|int $position_id): array
    {
        $attachments = $this->db()
            ->select(['attachment_id'])
            ->from($this->table('attachments'))
            ->where('component_type', $component->getType())
            ->where('component_name', $component->getName())
            ->where('position_type', $position_type)
            ->where('position_id', $position_id)
            ->get();
        foreach ($attachments as &$attachment) {
            $attachment = $this->getAttachment($attachment->attachment_id);
        }

        return $attachments;
    }

    /**
     * 첨부파일에 의해 첨부된 파일이 아닌, 실제 파일경로를 이용하여 파일 정보를 가져온다.
     *
     * @param string $path 첨부파일 고유값
     * @return \modules\attachment\dtos\File $file
     */
    public function getRawFile(string $path): ?\modules\attachment\dtos\File
    {
        if (isset(self::$_files[$path]) == true) {
            return self::$_files[$path];
        }

        if (is_file($path) == false) {
            return null;
        }

        return new \modules\attachment\dtos\File($path);
    }

    /**
     * 이미지파일의 너비 및 높이를 가져온다.
     *
     * @param string $path 첨부파일 고유값
     * @return int[] [$width, $height]
     */
    public function getImageSize(string $path): array
    {
        $type = $this->getFileType($this->getFileMime($path));

        switch ($type) {
            case 'svg':
                $svg = simplexml_load_string(\File::read($path));
                $width = intval($svg->attributes()->width);
                $height = intval($svg->attributes()->height);
                break;

            case 'icon':
            case 'image':
                $imagesize = getimagesize($path);
                $width = $imagesize[0];
                $height = $imagesize[1];
                break;

            default:
                $width = 0;
                $height = 0;
        }

        return [$width, $height];
    }

    /**
     * 파일의 MIME 데이터를 가져온다.
     *
     * @param string $path 파일경로
     * @return string $mime
     */
    public function getFileMime(string $path): string
    {
        if (is_file($path) == true) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $path);
            finfo_close($finfo);

            return $mime;
        } else {
            return '';
        }
    }

    /**
     * 파일의 MIME 값을 이용하여 파일종류를 정리한다.
     *
     * @param string $mime 파일 MIME
     * @return string $type 파일종류
     */
    public function getFileType(string $mime): string
    {
        if (preg_match('/^(.*?)\/(.*?)$/', $mime, $types) == true) {
            $type = $types[1];
            $detail = $types[2];

            switch ($type) {
                case 'image':
                    if (preg_match('/(icon|svg)/', $detail, $match) == true) {
                        return $match[1];
                    }

                    return 'image';

                case 'application':
                    if (
                        preg_match('/(pdf|officedocument|opendocument|word|powerpoint|excel|xml|rtf)/', $detail) == true
                    ) {
                        return 'document';
                    }

                    if (preg_match('/(zip|rar|tar|compressed)/', $detail) == true) {
                        return 'archive';
                    }

                    if (preg_match('/(json)/', $detail) == true) {
                        return 'text';
                    }

                    return 'file';

                case 'video':
                case 'audio':
                case 'text':
                    return $type;

                default:
                    return 'file';
            }
        } else {
            return 'file';
        }
    }

    /**
     * 파일의 확장자만 가져온다.
     *
     * @param string $name 파일명
     * @param string $mime 파일 MIME
     * @return string $extension 파일 확장자
     */
    public function getFileExtension(string $name, string $mime = null): string
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $replacement = [
            'jpeg' => 'jpg',
            'htm' => 'html',
        ];
        // @todo mime 체크

        return isset($replacement[$extension]) == true ? $replacement[$extension] : $extension;
    }

    /**
     * 출판된 파일의 출판위치를 이동하거나, 출판한다.
     *
     * @param ?string $attachment_id 파일고유값
     * @param \Component $component 이동할 컴포넌트객체
     * @param string $position_type 이동할 업로드위치종류
     * @param string|int $position_id 이동할 업로드위치고유값
     * @param bool $replacement 대치여부 (기본값 false)
     * @return bool $success
     */
    public function moveFile(
        ?string $attachment_id,
        \Component $component,
        string $position_type,
        string|int $position_id,
        bool $replacement = false
    ): bool|string {
        if ($attachment_id === null) {
            return true;
        }

        $attachment = $this->getAttachment($attachment_id);
        if ($attachment === null) {
            return false;
        }

        if ($attachment->isPublished() == false) {
            $this->publishFile($attachment_id, $component, $position_type, $position_id);
        } else {
            $this->db()
                ->update($this->table('attachments'), [
                    'component_type' => $component->getType(),
                    'component_name' => $component->getName(),
                    'position_type' => $position_type,
                    'position_id' => $position_id,
                ])
                ->where('attachment_id', $attachment_id)
                ->execute();
        }

        if ($replacement == true) {
            $deleteFiles = $this->db()
                ->select(['attachment_id'])
                ->from($this->table('attachments'))
                ->where('component_type', $component->getType())
                ->where('component_name', $component->getName())
                ->where('position_type', $position_type)
                ->where('position_id', $position_id)
                ->where('attachment_id', $attachment_id, '!=')
                ->get('attachment_id');
            $this->deleteFiles($deleteFiles);
        }

        unset(self::$_attachments[$attachment_id]);

        return true;
    }

    /**
     * 다중파일의 출판위치를 이동하거나 출판한다.
     *
     * @param string[] $attachment_ids 파일고유값
     * @param \Component $component 첨부한 컴포넌트객체
     * @param string $position_type 업로드위치종류
     * @param string|int $position_id 업로드위치고유값
     * @param bool $replacement 대치여부 (기본값 true)
     * @return bool $success
     */
    public function moveFiles(
        string|array $attachment_ids,
        \Component $component,
        string $position_type,
        string|int $position_id,
        bool $replacement = true
    ): bool {
        $success = true;
        foreach ($attachment_ids as $attachment_id) {
            $success = $success && $this->moveFile($attachment_id, $component, $position_type, $position_id);
        }

        if ($success === true && $replacement === true) {
            $deleteFiles = $this->db()
                ->select(['attachment_id'])
                ->from($this->table('attachments'))
                ->where('component_type', $component->getType())
                ->where('component_name', $component->getName())
                ->where('position_type', $position_type)
                ->where('position_id', $position_id);
            if (count($attachment_ids) > 0) {
                $deleteFiles->where('attachment_id', $attachment_ids, 'NOT IN');
            }
            $deleteFiles = $deleteFiles->get('attachment_id');
            $this->deleteFiles($deleteFiles);
        }

        return $success;
    }

    /**
     * 파일을 출판한다.
     *
     * @param ?string $attachment_id 파일고유값
     * @param \Component $component 첨부한 컴포넌트객체
     * @param string $position_type 업로드위치종류
     * @param string|int $position_id 업로드위치고유값
     * @param bool $replacement 대치여부 (기본값 false),
     * @return bool|string $success (이미 출판된 파일의 중복 출판으로 인해 파일고유값이 변경되었을 경우 해당 파일고유값)
     */
    public function publishFile(
        ?string $attachment_id,
        \Component $component,
        string $position_type,
        string|int $position_id,
        bool $replacement = false
    ): bool|string {
        $is_copied = false;
        if ($attachment_id === null) {
            if ($replacement == true) {
                $deleteFiles = $this->db()
                    ->select(['attachment_id'])
                    ->from($this->table('attachments'))
                    ->where('component_type', $component->getType())
                    ->where('component_name', $component->getName())
                    ->where('position_type', $position_type)
                    ->where('position_id', $position_id)
                    ->get('attachment_id');
                $this->deleteFiles($deleteFiles);
            }

            return true;
        }

        $attachment = $this->getAttachment($attachment_id);
        if ($attachment === null) {
            return false;
        }

        if ($attachment->isPublished() == false) {
            $hash = $attachment->getHash();
            if (strlen($hash) == 0) {
                $attachment->update();
                $hash = $attachment->getHash();
            }
            $file = $this->getFile($hash);
            if ($file === null) {
                if (is_file($attachment->getPath()) == false) {
                    return false;
                }

                $path = $this->getFileDir($hash) . '/' . $hash . '.' . \Format::random(4);
                $moved = \File::move($attachment->getPath(), \Configs::attachment() . '/' . $path);
                if ($moved == false) {
                    return false;
                }

                $this->db()
                    ->insert($this->table('files'), [
                        'hash' => $hash,
                        'path' => $path,
                        'type' => $attachment->getType(),
                        'mime' => $attachment->getMime(),
                        'extension' => $attachment->getExtension(),
                        'size' => $attachment->getSize(),
                        'width' => $attachment->getWidth(),
                        'height' => $attachment->getHeight(),
                        'created_at' => time(),
                    ])
                    ->execute();
            } elseif ($file->getPath() != $attachment->getPath()) {
                unlink($attachment->getPath());
            }

            $this->db()
                ->insert($this->table('attachments'), [
                    'attachment_id' => $attachment_id,
                    'hash' => $hash,
                    'component_type' => $component->getType(),
                    'component_name' => $component->getName(),
                    'position_type' => $position_type,
                    'position_id' => $position_id,
                    'name' => $attachment->getName(),
                    'created_at' => time(),
                    'extras' => $attachment->getExtras() === null ? null : \Format::toJson($attachment->getExtras()),
                ])
                ->execute();

            $this->db()
                ->delete($this->table('drafts'))
                ->where('draft_id', $attachment_id)
                ->execute();
        } else {
            if (
                $attachment->getComponent()->getType() != $component->getType() ||
                $attachment->getComponent()->getName() != $component->getName() ||
                $attachment->getPositionType() != $position_type ||
                $attachment->getPositionId() != $position_id
            ) {
                $is_copied = true;
                $attachment_id = $this->createDraftId($attachment->getName());
                $this->db()
                    ->insert($this->table('attachments'), [
                        'attachment_id' => $attachment_id,
                        'hash' => $attachment->getHash(),
                        'component_type' => $component->getType(),
                        'component_name' => $component->getName(),
                        'position_type' => $position_type,
                        'position_id' => $position_id,
                        'name' => $attachment->getName(),
                        'created_at' => time(),
                    ])
                    ->execute();
            }
        }

        if ($replacement == true) {
            $deleteFiles = $this->db()
                ->select(['attachment_id'])
                ->from($this->table('attachments'))
                ->where('component_type', $component->getType())
                ->where('component_name', $component->getName())
                ->where('position_type', $position_type)
                ->where('position_id', $position_id)
                ->where('attachment_id', $attachment_id, '!=')
                ->get('attachment_id');
            $this->deleteFiles($deleteFiles);
        }

        unset(self::$_attachments[$attachment_id]);

        return $is_copied === true ? $attachment_id : true;
    }

    /**
     * 다중파일을 출판한다.
     *
     * @param string[] $attachment_ids 파일고유값
     * @param \Component $component 첨부한 컴포넌트객체
     * @param string $position_type 업로드위치종류
     * @param string|int $position_id 업로드위치고유값
     * @param bool $replacement 대치여부 (기본값 true)
     * @return bool $success
     */
    public function publishFiles(
        string|array $attachment_ids,
        \Component $component,
        string $position_type,
        string|int $position_id,
        bool $replacement = true
    ): bool {
        $success = true;

        foreach ($attachment_ids as $attachment_id) {
            $success =
                $success && $this->publishFile($attachment_id, $component, $position_type, $position_id) !== false;
        }

        if ($success === true && $replacement === true) {
            $deleteFiles = $this->db()
                ->select(['attachment_id'])
                ->from($this->table('attachments'))
                ->where('component_type', $component->getType())
                ->where('component_name', $component->getName())
                ->where('position_type', $position_type)
                ->where('position_id', $position_id);
            if (count($attachment_ids) > 0) {
                $deleteFiles->where('attachment_id', $attachment_ids, 'NOT IN');
            }
            $deleteFiles = $deleteFiles->get('attachment_id');
            $this->deleteFiles($deleteFiles);
        }

        return $success;
    }

    /**
     * 첨부파일을 삭제한다.
     *
     * @param ?string $attachment_id 삭제할 첨부파일고유값
     * @return bool $success
     */
    public function deleteFile(?string $attachment_id): bool
    {
        if ($attachment_id === null) {
            return true;
        }

        $attachment = $this->getAttachment($attachment_id);
        if ($attachment === null) {
            return false;
        }

        if ($attachment->isPublished() == true) {
            $this->db()
                ->delete($this->table('attachments'))
                ->where('attachment_id', $attachment_id)
                ->execute();

            if (
                $this->db()
                    ->select()
                    ->from($this->table('attachments'))
                    ->where('hash', $attachment->getHash())
                    ->has() == false
            ) {
                $this->db()
                    ->delete($this->table('files'))
                    ->where('hash', $attachment->getHash())
                    ->execute();

                if (is_file($attachment->getPath()) == true) {
                    unlink($attachment->getPath());
                }

                if ($attachment->isResizable() == true) {
                    if (is_file($attachment->getPath() . '.view') == true) {
                        unlink($attachment->getPath() . '.view');
                    }

                    if (is_file($attachment->getPath() . '.thumbnail') == true) {
                        unlink($attachment->getPath() . '.thumbnail');
                    }
                }
            }
        } else {
            $this->db()
                ->delete($this->table('drafts'))
                ->where('draft_id', $attachment_id)
                ->execute();

            unlink($attachment->getPath());
            if ($attachment->isResizable() == true) {
                if (is_file($attachment->getPath() . '.view') == true) {
                    unlink($attachment->getPath() . '.view');
                }

                if (is_file($attachment->getPath() . '.thumbnail') == true) {
                    unlink($attachment->getPath() . '.thumbnail');
                }
            }
        }

        return true;
    }

    /**
     * 첨부파일을 삭제한다.
     *
     * @param string[] $attachment_ids 삭제할 첨부파일고유값
     * @return bool $success
     */
    public function deleteFiles(array $attachment_ids = []): bool
    {
        $success = true;
        foreach ($attachment_ids as $attachment_id) {
            $success = $success && $this->deleteFile($attachment_id);
        }

        return $success;
    }

    /**
     * 임시파일 고유값을 생성한다.
     *
     * @param string $base 고유값 생성을 위한 파일경로 또는 파일명
     */
    public function createDraftId(string $base): string
    {
        while (true) {
            $draft_id = \UUID::v1($base);
            if (
                $this->db()
                    ->select()
                    ->from($this->table('attachments'))
                    ->where('attachment_id', $draft_id)
                    ->has() == false
            ) {
                return $draft_id;
            }
        }
    }

    /**
     * 업로드를 시작하기 위해 파일명과 파일크기로 임시파일을 생성한다.
     *
     * @param string $name 파일명
     * @param int $size 파일크기
     * @return string $draft_id 임시파일고유값
     */
    public function createDraftByName(string $name, int $size, string $type = null): string
    {
        $draft_id = $this->createDraftId($name . $size);
        $this->db()
            ->insert($this->table('drafts'), [
                'draft_id' => $draft_id,
                'name' => $name,
                'type' => $type,
                'path' => $this->getDraftDir() . '/' . $draft_id . '-' . \Format::random(4),
                'extension' => $this->getFileExtension($name),
                'size' => $size,
                'created_at' => time(),
                'expired_at' => time() + 60 * 60 * 24,
            ])
            ->execute();

        return $draft_id;
    }

    /**
     * 경로상의 파일로 임시파일을 생성하고 파일을 임시파일경로로 이동한다.
     *
     * @param string $path 파일경로
     * @param mixed $extras 추가정보
     * @return string|bool $draft_id 임시파일고유값
     */
    public function createDraftByPath(string $path, mixed $extras = null): string|bool
    {
        if (is_file($path) == false) {
            return false;
        }

        $name = basename($path);
        $draft_id = $this->createDraftId($path);
        $draft_name = $draft_id . '-' . \Format::random(4);
        $this->db()
            ->insert($this->table('drafts'), [
                'draft_id' => $draft_id,
                'name' => $name,
                'path' => $this->getDraftDir() . '/' . $draft_name,
                'extension' => $this->getFileExtension($name),
                'size' => filesize($path),
                'created_at' => time(),
                'expired_at' => time() + 60 * 60 * 24,
                'extras' => $extras === null ? null : \Format::toJson($extras),
            ])
            ->execute();

        $success = \File::move($path, $this->getDraftPath() . '/' . $draft_name);
        if ($success == false) {
            $this->db()
                ->delete($this->table('drafts'))
                ->where('draft_id', $draft_id)
                ->execute();
            return false;
        }

        $updated = $this->getAttachment($draft_id)?->update();
        return $updated === true ? $draft_id : false;
    }

    /**
     * 기존 첨부파일로부터 임시파일을 생성한다.
     *
     * @param string $attachment_id 첨부파일고유값
     * @return string|bool $draft_id 임시파일고유값
     */
    public function createDraftByAttachment(string $attachment_id): string|bool
    {
        $attachment = $this->getAttachment($attachment_id);
        if ($attachment === null) {
            return false;
        }

        $draft_id = $this->createDraftId($attachment->getName());
        $this->db()
            ->insert($this->table('drafts'), [
                'draft_id' => $draft_id,
                'hash' => $attachment->getHash(),
                'name' => $attachment->getName(),
                'path' => $attachment->getPath(false),
                'type' => $attachment->getType(),
                'mime' => $attachment->getMime(),
                'extension' => $attachment->getExtension(),
                'size' => $attachment->getSize(),
                'created_at' => time(),
                'expired_at' => time() + 60 * 60 * 24,
            ])
            ->execute();

        return $draft_id;
    }

    /**
     * 썸네일을 생성한다.
     *
     * @param string $imgPath 썸네일을 생성할 대상 이미지 경로
     * @param string $thumbPath 썸네일이 저장될 경로
     * @param int $size 썸네일크기 (X축/Y축 중 더 큰축에 대한 리사이징 크기)
     * @param bool $is_delete 원본 이미지파일을 삭제할 지 여부
     * @param string $forceType 원본 이미지의 포맷과 무관하게 썸네일의 이미지포맷(JPG, GIF, PNG)를 지정할 경우 해당 포맷명
     * @return bool $success
     */
    public function createThumbnail(
        string $imgPath,
        string $thumbPath,
        int $size,
        bool $delete = false,
        ?string $forceType = null
    ): bool {
        $result = true;
        $imginfo = @getImageSize($imgPath);
        $extName = $imginfo[2];

        switch ($extName) {
            case '1':
                ($src = @imageCreateFromGIF($imgPath)) or ($result = false);
                $type = 'gif';
                break;
            case '2':
                ($src = @imageCreateFromJPEG($imgPath)) or ($result = false);
                $type = 'jpg';
                break;
            case '3':
                ($src = @imageCreateFromPNG($imgPath)) or ($result = false);
                $type = 'png';
                break;
            case '18':
                ($src = @imageCreateFromWebP($imgPath)) or ($result = false);
                $type = 'webp';
                break;

            default:
                $result = false;
        }

        if ($result == true) {
            $toType = $forceType ?? $type;

            if ($imginfo[0] < $imginfo[1]) {
                $height = $size;
                $width = ceil(($height * $imginfo[0]) / $imginfo[1]);
            } else {
                $width = $size;
                $height = ceil(($width * $imginfo[1]) / $imginfo[0]);
            }

            if ($toType == $type && ($imginfo[0] <= $width || $imginfo[1] <= $height)) {
                @copy($imgPath, $thumbPath);
                if ($delete == true) {
                    @unlink($imgPath);
                }
                return true;
            }

            $width = min($imginfo[0], $width);
            $height = min($imginfo[1], $height);

            $thumb = @imageCreateTrueColor($width, $height);

            switch ($toType) {
                case 'png':
                case 'webp':
                    $background = imageColorAllocate($src, 0, 0, 0);
                    imageColorTransparent($thumb, $background);
                    imageAlphaBlending($thumb, false);
                    imageSaveAlpha($thumb, true);
                    break;

                case 'gif':
                    $background = imageColorAllocate($src, 0, 0, 0);
                    imageColorTransparent($thumb, $background);
                    break;
            }

            @imageCopyResampled($thumb, $src, 0, 0, 0, 0, $width, $height, @imageSX($src), @imageSY($src)) or
                ($result = false);

            if ($toType == 'jpg') {
                @imageJPEG($thumb, $thumbPath, 80) or ($result = false);
            } elseif ($toType == 'gif') {
                @imageGIF($thumb, $thumbPath) or ($result = false);
            } elseif ($toType == 'png') {
                @imagePNG($thumb, $thumbPath) or ($result = false);
            } elseif ($toType == 'webp') {
                @imageWebP($thumb, $thumbPath, 80) or ($result = false);
            } else {
                $result = false;
            }
            @imageDestroy($src);
            @imageDestroy($thumb);
            @chmod($thumbPath, 0755);
        }

        if ($delete == true) {
            @unlink($imgPath);
        }

        return $result;
    }

    /**
     * 썸네일을 생성할때 지정된 가로 및 세로크기에 맞춰 비율에 따라 원본이미지를 자른 후 저장한다.
     *
     * @param string $imgPath 썸네일을 생성할 대상 이미지 경로
     * @param string $thumbPath 썸네일이 저장될 경로
     * @param int $width 썸네일 가로크기
     * @param int $height 썸네일 세로크기
     * @param bool $is_delete 원본 이미지파일을 삭제할 지 여부
     * @param string $forceType 원본 이미지의 포맷과 무관하게 썸네일의 이미지포맷(JPG, GIF, PNG)를 지정할 경우 해당 포맷명
     * @return bool $success
     */
    public function cropThumbnail(
        string $imgPath,
        string $thumbPath,
        int $width,
        int $height,
        bool $delete = false,
        ?string $forceType = null
    ): bool {
        $result = true;
        $imginfo = @getimagesize($imgPath);
        $extName = $imginfo[2];

        if ($imginfo[0] == $width && $imginfo[1] == $height) {
            @copy($imgPath, $thumbPath);
            if ($delete == true) {
                @unlink($imgPath);
            }
            return true;
        }

        switch ($extName) {
            case '2':
                ($src = @ImageCreateFromJPEG($imgPath)) or ($result = false);
                $type = 'jpg';
                break;
            case '1':
                ($src = @ImageCreateFromGIF($imgPath)) or ($result = false);
                $type = 'gif';
                break;
            case '3':
                ($src = @ImageCreateFromPNG($imgPath)) or ($result = false);
                $type = 'png';
                break;
            default:
                $result = false;
        }

        if ($result == true) {
            if ($width * $imginfo[1] < $height * $imginfo[0]) {
                $rs_img_width = round($imginfo[1] * ($width / $height));
                $rs_img_height = $imginfo[1];

                $x = round(($imginfo[0] - $rs_img_width) / 2);
                $y = 0;
            } else {
                $rs_img_width = $imginfo[0];
                $rs_img_height = round($imginfo[0] * ($height / $width));

                $x = 0;
                $y = round(($imginfo[1] - $rs_img_height) / 2);
            }

            $sc_img_width = $rs_img_width;
            $sc_img_height = $rs_img_height;

            $crop = @ImageCreateTrueColor($rs_img_width, $rs_img_height);

            switch ($type) {
                case 'png':
                    $background = imagecolorallocate($src, 0, 0, 0);
                    imagecolortransparent($crop, $background);
                    imagealphablending($crop, false);
                    imagesavealpha($crop, true);
                    break;

                case 'gif':
                    $background = imagecolorallocate($src, 0, 0, 0);
                    imagecolortransparent($src, $background);
                    break;
            }

            @ImageCopyResampled(
                $crop,
                $src,
                0,
                0,
                $x,
                $y,
                $rs_img_width,
                $rs_img_height,
                $rs_img_width,
                $rs_img_height
            ) or ($result = false);

            if ($result == true) {
                $thumb = @ImageCreateTrueColor($width, $height);
                switch ($type) {
                    case 'png':
                        $background = imagecolorallocate($crop, 0, 0, 0);
                        imagecolortransparent($thumb, $background);
                        imagealphablending($thumb, false);
                        imagesavealpha($thumb, true);
                        break;

                    case 'gif':
                        $background = imagecolorallocate($crop, 0, 0, 0);
                        imagecolortransparent($crop, $background);
                        break;
                }

                @ImageCopyResampled($thumb, $crop, 0, 0, 0, 0, $width, $height, $rs_img_width, $rs_img_height) or
                    ($result = false);
            }

            $type = $forceType != null ? $forceType : $type;

            if ($type == 'jpg') {
                @ImageJPEG($thumb, $thumbPath, 100) or ($result = false);
            } elseif ($type == 'gif') {
                @ImageGIF($thumb, $thumbPath) or ($result = false);
            } elseif ($type == 'png') {
                @imagePNG($thumb, $thumbPath) or ($result = false);
            } else {
                $result = false;
            }
            @ImageDestroy($src);
            @ImageDestroy($thumb);
            @ImageDestroy($crop);
            @chmod($thumbPath, 0755);
        }

        if ($delete == true) {
            @unlink($imgPath);
        }

        return $result;
    }

    /**
     * 파일 라우팅을 처리한다.
     *
     * @param \Route $route 현재경로
     * @param string $request 요청파일경로 (drafts, attachments, files)
     * @param string $type 파일접근종류 (origin, view, thumbnail, download)
     * @param int $file_id 파일고유값
     * @param string $name 파일명
     */
    public function doRoute(\Route $route, string $request, string $type, string $attachment_id, string $name): void
    {
        $attachment = $this->getAttachment($attachment_id);
        if ($attachment === null || is_file($attachment->getPath()) == false) {
            \ErrorHandler::print($this->error('NOT_FOUND_FILE', $route->getUrl()));
        }

        \iModules::session_stop();

        if ($type == 'thumbnail' && $attachment->isResizable() == false) {
            $type = 'view';
        }

        if ($type == 'view' && $attachment->isViewable() == false) {
            $type = 'download';
        }

        $path = $attachment->getPath();
        $size = $attachment->getSize();
        $mime = $attachment->getMime();

        if ($type == 'thumbnail') {
            if (is_file($path . '.thumbnail') == true) {
                $path = $path . '.thumbnail';
                $size = filesize($path);
                $mime = 'image/webp';
            } else {
                $success = $this->createThumbnail($path, $path . '.thumbnail', 600, false, 'webp');
                if ($success == true) {
                    $path = $path . '.thumbnail';
                    $size = filesize($path);
                    $mime = 'image/webp';
                }
            }
        }

        if ($type == 'view' && $attachment->isResizable() == true) {
            if (is_file($path . '.view') == true) {
                $path = $path . '.view';
                $size = filesize($path);
                $mime = 'image/webp';
            } else {
                $success = $this->createThumbnail($path, $path . '.view', 1600, false, 'webp');
                if ($success == true) {
                    $path = $path . '.view';
                    $size = filesize($path);
                    $mime = 'image/webp';
                }
            }
        }

        \Header::type($mime);
        \Header::length($size);
        if ($type == 'download') {
            \Header::attachment($name);
        } else {
            \Header::cache(3600);
        }

        readfile($path);
        exit();
    }

    /**
     * 특수한 에러코드의 경우 에러데이터를 현재 클래스에서 처리하여 에러클래스로 전달한다.
     *
     * @param string $code 에러코드
     * @param ?string $message 에러메시지
     * @param ?object $details 에러와 관련된 추가정보
     * @return \ErrorData $error
     */
    public function error(string $code, ?string $message = null, ?object $details = null): \ErrorData
    {
        switch ($code) {
            case 'NOT_FOUND_FILE':
                $error = \ErrorHandler::data($code);
                $error->message = $this->getErrorText($code);
                $error->suffix = $message;
                return $error;

            default:
                return parent::error($code, $message, $details);
        }
    }
}
