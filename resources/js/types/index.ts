import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    isAdmin: boolean;
}

export interface Invitation {
    id: number;
    name: string;
    email: string;
    role: 'ADMIN' | 'EMPLOYEE';
    regime: 'DIA' | 'NOITE' | 'HIBRIDO';
    regime_label: string;
    contract: 'H37_30' | 'H40';
    contract_label: string;
    fixa_noite: boolean;
    status: 'pending' | 'accepted' | 'expired' | 'revoked';
    expires_at: string;
    accept_url: string | null;
    whatsapp_url: string | null;
}

export interface ShiftType {
    id: number;
    code: string;
    name: string;
    starts_at: string;
    ends_at: string;
    hours: number;
    color: string;
}

export interface CoverageEntry {
    shift_type_id: number;
    weekday: number;
    required: number;
}

export interface RuleConfigs {
    hour_bank_weekly_tolerance: number;
    max_consecutive_work_days: number;
    ff_window_weeks: number;
    ff_monthly: boolean;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
