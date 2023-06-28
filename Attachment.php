<?php
/**
 * 이 파일은 아이모듈 첨부파일모듈의 일부입니다. (https://www.imodules.io)
 *
 * 첨부파일 클래스를 정의한다.
 * 첨부된 파일데이터를 관리하고, 첨부파일 업로드가 필요한 곳에 파일업로더를 제공한다.
 *
 * @file /modules/attachment/Attachment.php
 * @author Arzz <arzz@arzz.com>
 * @license MIT License
 * @modified 2023. 6. 27.
 */
namespace modules\attachment;
class Attachment extends \Module
{
    /**
     * @var \modules\attachment\dto\File[] $_files 파일정보를 보관한다.
     */
    private static array $_files = [];

    /**
     * @var \modules\attachment\dto\Draft[] $_drafts 임시파일정보를 보관한다.
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
     * @return ?\modules\attachment\dto\File $file
     */
    public function getFile(string $hash): ?\modules\attachment\dto\File
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

            self::$_files[$hash] = new \modules\attachment\dto\File($file);
        }

        return self::$_files[$hash];
    }

    /**
     * 첨부파일 정보를 가져온다.
     *
     * @param string $attachment_id 첨부파일고유값
     * @return ?\modules\attachment\dto\Attachment $attachment
     */
    public function getAttachment(string $attachment_id): ?\modules\attachment\dto\Attachment
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

            self::$_attachments[$attachment_id] = new \modules\attachment\dto\Attachment($attachment);
        }

        return self::$_attachments[$attachment_id];
    }

    /**
     * 첨부파일에 의해 첨부된 파일이 아닌, 실제 파일경로를 이용하여 파일 정보를 가져온다.
     *
     * @param string $path 첨부파일 고유값
     * @return \modules\attachment\dto\File $file
     */
    public function getRawFile(string $path): ?\modules\attachment\dto\File
    {
        if (isset(self::$_files[$path]) == true) {
            return self::$_files[$path];
        }

        if (is_file($path) == false) {
            return null;
        }

        return new dto\File($path);
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
     * 파일을 출판한다.
     *
     * @param ?string $attachment_id 파일고유값
     * @param \Component $component 첨부한 컴포넌트객체 또는 [컴포넌트타입, 컴포넌트명]
     * @param string $position_type 업로드위치
     * @param string|int $position_id 업로드고유값
     * @param bool $replacement 대치여부 (기본값 false)
     * @return bool $success
     */
    public function publishFile(
        ?string $attachment_id,
        \Component $component,
        string $position_type,
        string|int $position_id,
        bool $replacement = false
    ): bool {
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
            $file = $this->getFile($hash);
            if ($file === null) {
                if (is_file($attachment->getPath()) == false) {
                    return false;
                }

                $path = $this->getFileDir($hash) . '/' . $hash . '.' . \Format::random(4);
                $moved = rename($attachment->getPath(), \Configs::attachment() . '/' . $path);
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
                ])
                ->execute();

            $this->db()
                ->delete($this->table('drafts'))
                ->where('draft_id', $attachment_id)
                ->execute();
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

        return true;
    }

    /**
     * 다중파일을 출판한다.
     *
     * @param string[] $attachment_ids 파일고유값
     * @param \Component $component 첨부한 컴포넌트객체 또는 [컴포넌트타입, 컴포넌트명]
     * @param string $position_type 업로드위치
     * @param string|int $position_id 업로드고유값
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
            $success = $success && $this->publishFile($attachment_id, $component, $position_type, $position_id);
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
     * @param string $attachment_id 삭제할 첨부파일고유값
     * @return bool $success
     */
    public function deleteFile(string $attachment_id): bool
    {
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
     * 파일 라우팅을 처리한다.
     *
     * @param Route $route 현재경로
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

        session_write_close();

        if ($attachment->getType() == 'image') {
            $path = $attachment->getPath();
        } else {
            $path = $attachment->getPath();
        }

        if ($type != 'download') {
            header('Content-Type: ' . $attachment->getMime());
            header('Content-Length: ' . filesize($path));
            header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 3600) . ' GMT');
            header('Cache-Control: max-age=3600');
            header('Pragma: public');

            readfile($path);
            exit();
        } else {
            header('Content-Type: ' . $attachment->getMime());
            header('Content-Length: ' . filesize($path));
            header('Expires: 0');
            header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
            header('Cache-Control: private', false);
            header('Pragma: public');
            header('Expires: 0');
            header(
                'Content-Disposition: attachment; filename="' .
                    rawurlencode($name) .
                    '"; filename*=UTF-8\'\'' .
                    rawurlencode($name)
            );
            header('Content-Transfer-Encoding: binary');

            readfile($path);
            exit();
        }
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
                $error = \ErrorHandler::data();
                $error->message = $this->getErrorText($code);
                $error->suffix = $message;
                return $error;

            default:
                return parent::error($code, $message, $details);
        }
    }
}
