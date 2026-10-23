import '@inertiajs/core';

export type Role = 'owner' | 'staff' | 'customer';

/** The signed-in user, as HandleInertiaRequests shares it. */
export interface AuthUser {
    id: number;
    name: string;
    email: string;
    role: Role;
    /** What the navigation may offer; every route still authorizes itself. */
    can: {
        viewMembers: boolean;
        manageClasses: boolean;
        updateSettings: boolean;
        /** Customers buy memberships and class packs. */
        buy: boolean;
    };
}

/** The current studio. Null off a tenant subdomain. */
export interface CurrentTenant {
    name: string;
    slug: string;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            appName: string;
            tenant: CurrentTenant | null;
            auth: { user: AuthUser | null };
        };
    }
}
