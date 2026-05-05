<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Kullanıcı yetki kontrollerini yönetir.
 */
class BorsatekPermissions {

    /**
     * Geçerli kullanıcının eklentiye erişim iznine sahip olup olmadığını döndürür.
     */
    public function currentUserAllowed(): bool {
        $allowed = (array) get_option( 'borsatek_ai_allowed_users', [] );
        if ( empty( $allowed ) ) {
            return current_user_can( 'edit_posts' );
        }
        return in_array( get_current_user_id(), array_map( 'intval', $allowed ), true );
    }

    /**
     * Geçerli kullanıcının WordPress yöneticisi olup olmadığını döndürür.
     */
    public function currentUserIsAdmin(): bool {
        return current_user_can( 'manage_options' );
    }

    /**
     * Geçerli kullanıcının haber akışına erişip erişemeyeceğini döndürür.
     */
    public function canAccessStream(): bool {
        return current_user_can( 'edit_posts' ) && $this->currentUserAllowed();
    }

    /**
     * Geçerli kullanıcının ayarlara erişip erişemeyeceğini döndürür.
     */
    public function canAccessSettings(): bool {
        return $this->currentUserIsAdmin();
    }

    /**
     * Ayarlarda tanımlı aktif kullanıcı ID'sini döndürür.
     */
    public function getActiveUser(): int {
        return (int) get_option( 'borsatek_ai_active_user', get_current_user_id() );
    }

    /**
     * İzin verilen kullanıcı ID listesini döndürür.
     */
    public function getAllowedUsers(): array {
        return (array) get_option( 'borsatek_ai_allowed_users', [] );
    }
}
