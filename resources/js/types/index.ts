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

export interface ViabilityBalance {
    shifts_per_week: number;
    hours_per_week: number;
}

export interface Viability {
    status: 'ok' | 'tight' | 'deficit';
    employees_count: number;
    hour_bank_weekly_tolerance: number;
    demand: {
        shifts_per_week: number;
        hours_per_week: number;
    };
    supply: {
        contractual: ViabilityBalance;
        with_hour_bank: ViabilityBalance;
    };
    balance: {
        contractual: ViabilityBalance;
        with_hour_bank: ViabilityBalance;
    };
    night: {
        required_shifts_per_week: number;
        pool_size: number;
        pool_ok: boolean;
        min_pool_size: number;
    };
    suggestions: string[];
}

export interface ScheduleSummary {
    id: number;
    period_start: string;
    period_end: string;
    label: string;
    status: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED';
    generated_at: string | null;
    published_at: string | null;
}

export interface ScheduleMeta {
    id: number;
    period_start: string;
    period_end: string;
    status: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED';
    generated_at?: string | null;
    published_at: string | null;
    solver_stats?: SolverStats | null;
}

export interface SolverStats {
    status: 'FEASIBLE' | 'INFEASIBLE' | 'TIMEOUT' | 'UNAVAILABLE';
    objective?: number | null;
    wall_time_s?: number | null;
    conflicts?: SolverViolation[];
    error?: string;
}

export interface SolverViolation {
    rule: string;
    message: string;
    date?: string | null;
    employee_id?: number | null;
}

export interface ScheduleDate {
    date: string;
    day: number;
    weekday_label: string;
    is_weekend: boolean;
    is_current_week?: boolean;
}

export interface ScheduleCell {
    date: string;
    shift_code: string | null;
    shift_type_id: number | null;
    is_day_off: boolean;
    assignment_id: number | null;
}

export interface ScheduleEmployeeRow {
    employee_id: number;
    name: string;
    cells: ScheduleCell[];
    total_hours: number;
    avg_weekly_hours: number;
    days_off: number;
    weekends_worked: number;
    is_self?: boolean;
}

export interface ScheduleDayFooterShift {
    shift_type_id: number;
    code: string;
    required: number;
    actual: number;
    ok: boolean;
}

export interface ScheduleDayFooter {
    date: string;
    is_weekend: boolean;
    shifts: ScheduleDayFooterShift[];
}

export interface DashboardMonthSchedule {
    id: number;
    status: 'DRAFT' | 'PUBLISHED' | 'ARCHIVED';
    label: string;
}

export interface DashboardThisMonth {
    schedule: DashboardMonthSchedule | null;
    pending_swaps: number;
    pending_vacations: number;
    pending_invitations: number;
}

export interface EquityEmployee {
    employee_id: number;
    name: string;
    total_hours: number;
    weekends_worked: number;
    days_off: number;
    contractual_hours: number;
    hour_bank_balance: number;
    hour_bank_label: string;
    bar_pct: number;
}

export interface DashboardEquity {
    schedule: { id: number; label: string };
    employees: EquityEmployee[];
    max_hours_employee_id: number | null;
    min_hours_employee_id: number | null;
}

export interface AdminDashboardStats {
    this_month: DashboardThisMonth;
    equity: DashboardEquity | null;
}

export interface DashboardNextShift {
    date: string;
    shift_code: string;
}

export interface DashboardWeekCell {
    date: string;
    day: number;
    weekday_label: string;
    is_weekend: boolean;
    is_today: boolean;
    shift_code: string | null;
    is_day_off: boolean;
}

export interface EmployeeDashboardStats {
    next_shift: DashboardNextShift | null;
    current_week: DashboardWeekCell[];
    pending_swaps: number;
    pending_vacations: number;
}

export interface VacationImpact {
    ok: boolean;
    issues: SolverViolation[];
    no_schedule?: boolean;
}

export interface VacationRequestItem {
    id: number;
    start_date: string;
    end_date: string;
    status: 'PENDING' | 'APPROVED' | 'DECLINED' | 'CANCELLED';
    note: string | null;
    decided_at: string | null;
    created_at: string;
}

export interface AdminVacationRequestItem extends VacationRequestItem {
    employee_id: number;
    employee_name: string;
    impact: VacationImpact | null;
    decided_by_name: string | null;
}

export interface AbsenceEmployeeOption {
    id: number;
    name: string;
}

export interface AbsenceCoverageGap {
    date: string;
    shift_code: string;
    before: number;
    after: number;
    required: number;
}

export interface Absence {
    id: number;
    employee_id: number;
    employee_name: string;
    start_date: string;
    end_date: string;
    type: 'SICK' | 'UNJUSTIFIED' | 'OTHER';
    type_label: string;
    note: string | null;
    coverage_gaps: AbsenceCoverageGap[];
    schedule_id: number | null;
    reoptimizable_from: string | null;
    reoptimized_at: string | null;
    reoptimization_status: 'FEASIBLE' | 'INFEASIBLE' | 'UNAVAILABLE' | null;
    reoptimization_conflicts: SolverViolation[] | null;
}

export interface AuditLogEntry {
    id: number;
    created_at: string | null;
    actor_name: string;
    action: string;
    subject_type: string | null;
    subject_id: number | null;
    changes_summary: string | null;
}

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number | null;
    to: number | null;
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
