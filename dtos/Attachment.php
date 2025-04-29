<?php
/**
 * 이 파일은 아이모듈 첨부파일모듈의 일부입니다. (https://www.imodules.io)
 *
 * 첨부파일모듈에 의해 첨부된 파일 구조체를 정의한다.
 *
 * @file /modules/attachment/dtos/Attachment.php
 * @author sungjin <esung246@naddle.net>
 * @license MIT License
 * @modified 2025. 4. 29.
 */
namespace modules\attachment\dtos;
class Attachment
{
    /**
     * @var string $_id 첨부파일고유값
     */
    private string $_id;

    /**
     * @var string $_hash 파일해시
     */
    private string $_hash;

    /**
     * @var string $_component_type 첨부한 컴포넌트종류
     */
    private string $_component_type;

    /**
     * @var string $_component_name 첨부한 컴포넌트명
     */
    private string $_component_name;

    /**
     * @var string $_position_type 업로드위치
     */
    private string $_position_type;

    /**
     * @var string $_position_id 업로드위치고유값
     */
    private string $_position_id;

    /**
     * @var string $_name 파일명
     */
    private string $_name;

    /**
     * @var string $_path 파일절대경로
     */
    private string $_path;

    /**
     * @var string $_type 파일종류
     */
    private string $_type;

    /**
     * @var string $_mime 파일 MIME
     */
    private string $_mime;

    /**
     * @var string $_extension 파일 확장자
     */
    private string $_extension;

    /**
     * @var int $_size 파일용량
     */
    private int $_size;

    /**
     * @var int $_width 이미지파일인 경우 이미지파일 가로크기
     */
    private int $_width;

    /**
     * @var int $_height 이미지파일인 경우 이미지파일 세로크기
     */
    private int $_height;

    /**
     * @var int $_created_at 생성일시
     */
    private int $_created_at;

    /**
     * @var int $_expired_at 업로드대기만료일시
     */
    private int $_expired_at;

    /**
     * @var int $_downloads 다운로드수
     */
    private int $_downloads;

    /**
     * @var mixed $_extras 추가정보
     */
    private mixed $_extras;

    /**
     * 파일 구조체를 정의한다.
     *
     * @param object $attachment 첨부파일정보
     */
    public function __construct(object $attachment)
    {
        if (isset($attachment->attachment_id) == true) {
            $this->_id = $attachment->attachment_id;
            $this->_component_type = $attachment->component_type;
            $this->_component_name = $attachment->component_name;
            $this->_position_type = $attachment->position_type;
            $this->_position_id = $attachment->position_id;
            $this->_downloads = $attachment->downloads;
            $this->_extras = json_decode($attachment->extras ?? '');
        } else {
            $this->_id = $attachment->draft_id;
            $this->_extras = json_decode($attachment->extras ?? '');
        }

        if ($attachment->hash !== null) {
            $this->_hash = $attachment->hash;
        }

        $this->_name = $attachment->name;
        $this->_path = $attachment->path;

        if ($attachment->type !== null) {
            $this->_type = $attachment->type;
        }

        if ($attachment->mime !== null) {
            $this->_mime = $attachment->mime;
        }

        if ($attachment->extension !== null) {
            $this->_extension = $attachment->extension;
        }
        $this->_size = $attachment->size;

        $this->_width = $attachment->width ?? 0;
        $this->_height = $attachment->height ?? 0;
        $this->_created_at = $attachment->created_at;
        $this->_expired_at = $attachment->expired_at ?? 0;
    }

    /**
     * 첨부파일고유값을 가져온다.
     *
     * @return string $id
     */
    public function getId(): string
    {
        return $this->_id;
    }

    /**
     * 파일해시를 가져온다.
     *
     * @return string $hash
     */
    public function getHash(): string
    {
        return isset($this->_hash) == true ? $this->_hash : '';
    }

    /**
     * 파일명을 가져온다.
     *
     * @return string $name
     */
    public function getName(): string
    {
        return $this->_name;
    }

    /**
     * 파일 기본아이콘을 가져온다.
     *
     * @return string $icon
     */
    public function getIcon(): string
    {
        $icon = 'file';

        /**
         * @var \modules\attachment\Attachment $mAttachment
         */
        $mAttachment = \Modules::get('attachment');
        if (in_array($this->getType(), ['archive', 'audio', 'document', 'image', 'video']) == true) {
            $icon = 'file_type_' . $this->_type;
        }

        if (in_array($this->getExtension(), ['doc', 'docx', 'hwp', 'pdf', 'ppt', 'pptx', 'xls', 'xlsx']) == true) {
            $icon = 'file_extension_' . substr($this->_type, 0, 3);
        }

        if ($this->getExtension() == 'svg') {
            $icon = 'file_type_image';
        }

        return $mAttachment->getDir() . '/images/' . $icon . '.png';
    }

    /**
     * 파일명을 변경한다.
     *
     * @param string $name 변경할 파일명
     * @param bool $extension 파일확장자 포함여부
     */
    public function setName(string $name, bool $extension = false): \modules\attachment\dtos\Attachment
    {
        if ($extension == true) {
            $this->_name = $name;
        } else {
            $this->_name = $name . '.' . $this->getExtension();
        }

        /**
         * @var \modules\attachment\Attachment $mAttachment
         */
        $mAttachment = \Modules::get('attachment');
        if ($this->isPublished() == true) {
            $mAttachment
                ->db()
                ->update($mAttachment->table('attachments'), ['name' => $this->_name])
                ->where('attachment_id', $this->_id)
                ->execute();
        } else {
            $mAttachment
                ->db()
                ->update($mAttachment->table('drafts'), ['name' => $this->_name])
                ->where('draft_id', $this->_id)
                ->execute();
        }

        return $this;
    }

    /**
     * 파일 절대경로를 가져온다.
     *
     * @return string $path
     */
    public function getPath(bool $is_full_path = true): string
    {
        $path = '';
        if ($is_full_path == true) {
            $path .= \Configs::attachment() . '/';
        }
        $path .= $this->_path;

        return $path;
    }

    /**
     * 파일종류를 가져온다.
     *
     * @return string $type
     */
    public function getType(): string
    {
        return isset($this->_type) == true ? $this->_type : '';
    }

    /**
     * 파일 MIME를 가져온다.
     *
     * @return string $mime
     */
    public function getMime(): string
    {
        return isset($this->_mime) == true ? $this->_mime : '';
    }

    /**
     * 파일 확장자를 가져온다.
     *
     * @return string $extension
     */
    public function getExtension(): string
    {
        return isset($this->_extension) == true ? $this->_extension : '';
    }

    /**
     * 파일크기를 가져온다.
     *
     * @return int $size
     */
    public function getSize(): int
    {
        return $this->_size;
    }

    /**
     * 이미지파일 너비를 가져온다.
     *
     * @param int $width
     */
    public function getWidth(): int
    {
        return $this->_width;
    }

    /**
     * 이미지파일 높이를 가져온다.
     *
     * @param int $height
     */
    public function getHeight(): int
    {
        return $this->_height;
    }

    /**
     * 이미지파일을 출력할때 사용할 이미지 스타일을 가져온다.
     *
     * @param ?string $style 추가할 스타일
     * @return ?string $style 이미지 크기가 포함된 스타일
     */
    public function getStyle(?string $style = null): ?string
    {
        $styles = new \stdClass();
        $tokens = $style === null ? [] : explode(';', $style);
        foreach ($tokens as $token) {
            $temp = explode(':', $token);
            $name = strtolower(array_shift($temp));
            $value = implode(':', $temp);
            $styles->{$name} = $value;
        }

        $styles->width ??= $this->_width . 'px';

        $width = null;
        $height = null;
        if ($this->_width > 0 && $this->_height > 0) {
            $width = $styles->width;
            $height = $styles->height ?? $this->_height;
        }

        if ($width !== null && $height !== null) {
            $ratioX = $ratioY = null;
            if (preg_match('/([0-9]+)/', $width, $match) == true) {
                $ratioX = $match[1];
            }

            if (preg_match('/([0-9]+)/', $height, $match) == true) {
                $ratioY = $match[1];
            }

            $styles->{'aspect-ratio'} = $ratioX . '/' . $ratioY;
        }

        $style = [];
        foreach ($styles as $key => $value) {
            $style[] = $key . ': ' . $value;
        }

        return count($style) == 0 ? null : implode('; ', $style);
    }

    /**
     * 생성일자를 가져온다.
     *
     * @param int $created_at
     */
    public function getCreatedAt(): int
    {
        return $this->_created_at;
    }

    /**
     * 파일을 첨부한 컴포넌트를 가져온다.
     *
     * @return ?\Component $component
     */
    public function getComponent(): ?\Component
    {
        return \Component::get($this->_component_type, $this->_component_name);
    }

    /**
     * 파일이 첨부된 위치종류를 가져온다.
     *
     * @return ?string $position_type
     */
    public function getPositionType(): ?string
    {
        return isset($this->_position_type) == true ? $this->_position_type : null;
    }

    /**
     * 파일이 첨부된 위치 고유값을 가져온다.
     *
     * @return string|int $position_id
     */
    public function getPositionId(): string|int
    {
        return isset($this->_position_id) == true ? $this->_position_id : null;
    }

    /**
     * 파일 추가정보를 가져온다.
     *
     * @return mixed $extras
     */
    public function getExtras(): mixed
    {
        return $this->_extras;
    }

    /**
     * 파일정보를 갱신한다.
     *
     * @return bool $success
     */
    public function update(): bool
    {
        /**
         * @var \modules\attachment\Attachment $mAttachment
         */
        $mAttachment = \Modules::get('attachment');
        if (is_file($this->getPath()) == true) {
            $file = $mAttachment->getRawFile($this->getPath());
            if ($file === null) {
                return false;
            }

            if ($this->isPublished() == true) {
                if ($file->getHash() !== $this->getHash()) {
                    // @todo 파일이 변경됨
                    return false;
                }
            } else {
                $update = [
                    'hash' => $file->getHash(),
                    'name' => $file->getName(
                        $this->getName(),
                        $mAttachment->getFileExtension($this->getName(), $file->getMime())
                    ),
                    'type' => $file->getType(),
                    'mime' => $file->getMime(),
                    'extension' => $mAttachment->getFileExtension($this->getName(), $file->getMime()),
                    'width' => $file->getWidth(),
                    'height' => $file->getHeight(),
                ];

                $mAttachment
                    ->db()
                    ->update($mAttachment->table('drafts'), $update)
                    ->where('draft_id', $this->getId())
                    ->execute();

                $this->_hash = $update['hash'];
                $this->_name = $update['name'];
                $this->_type = $update['type'];
                $this->_mime = $update['mime'];
                $this->_extension = $update['extension'];
                $this->_width = $update['width'];
                $this->_height = $update['height'];
            }

            return true;
        }

        return false;
    }

    /**
     * 파일 URL 을 가져온다.
     *
     * @param ?string $type URL종류 (thumbnail : 이미지썸네일, view : 이미지보기, origin : 원본, download : 다운로드, NULL인 경우 파일 종류에 따라 자동으로 선택)
     * @param bool $is_full_url 도메인을 포함한 전체 URL 을 가져올지 여부
     * @return string $url
     */
    public function getUrl(?string $type = null, bool $is_full_url = false): string
    {
        if ($type === null) {
            if (in_array($this->_type, ['image', 'text']) == true) {
                $type = 'view';
            } else {
                $type = 'download';
            }
        }

        if (($type == 'thumbnail' || $type == 'view') && $this->isResizable() == true) {
            $name = preg_replace('/\.([a-z]+)$/', '.webp', $this->_name);
        } else {
            $name = $this->_name;
        }
        $route = '/files/' . $type . '/' . $this->_id . '/' . urlencode($name);

        $url = '';
        if ($is_full_url === true) {
            $url .= \Domains::get()->getUrl();
        }
        $url .= \Configs::dir() . (\Domains::has()?->isRewrite() == true ? $route : '/?route=' . $route);

        return $url;
    }

    /**
     * 파일이 브라우저를 통해 보여질 수 있는 이미지인지 확인한다.
     *
     * @return bool $is_image
     */
    public function isImage(): bool
    {
        return in_array($this->getType(), ['image', 'svg', 'icon']) == true;
    }

    /**
     * 파일이 썸네일을 생성할 수 있는지 확인한다.
     *
     * @return bool $resizable
     */
    public function isResizable(): bool
    {
        return in_array($this->getType(), ['image']) == true;
    }

    /**
     * 파일을 브라우저에서 볼 수 있는지 확인한다.
     *
     * @return bool $viewable
     */
    public function isViewable(): bool
    {
        return in_array($this->getType(), ['image', 'svg', 'icon', 'text', 'video']) == true ||
            in_array($this->getExtension(), ['pdf']) == true;
    }

    /**
     * 첨부파일이 출판된 상태인지 확인한다.
     *
     * @return bool $is_published
     */
    public function isPublished(): bool
    {
        return isset($this->_component_type) == true && isset($this->_component_name) == true;
    }

    /**
     * 파일정보를 JSON 으로 가져온다.
     *
     * @return object $json
     */
    public function getJson(): object
    {
        $attachment = new \stdClass();

        $attachment->id = $this->_id;
        $attachment->icon = $this->getIcon();
        $attachment->name = $this->_name;
        $attachment->type = $this->getType();
        $attachment->mime = $this->getMime();
        $attachment->extension = $this->getExtension();
        $attachment->size = $this->_size;
        $attachment->width = $this->getWidth();
        $attachment->height = $this->getHeight();
        $attachment->created_at = $this->_created_at;
        $attachment->expired_at = $this->isPublished() == true ? null : $this->_expired_at;
        $attachment->view = $this->isViewable() == true ? $this->getUrl('view') : null;
        $attachment->download = $this->getUrl('download');
        $attachment->thumbnail = $this->isImage() == true ? $this->getUrl('thumbnail') : null;

        return $attachment;
    }
}
