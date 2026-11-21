import { useEffect, useState } from 'react';
import { Link, NavLink, Navigate, Outlet, useLocation, useNavigate } from 'react-router-dom';
import Icon from './icons';
import { Badge, Loading } from './ui';
import { homeFor, useAuth } from '../lib/auth';
import { useMeta } from '../lib/hooks';
import { label } from '../lib/format';

export function Logo({ light }) {
    return (
        <Link to="/" className="flex items-center gap-2" aria-label="Bhatkal Mobile Hub home">
            <span className="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-brand-500 to-brand-700 text-white shadow-sm"><Icon name="smartphone" className="h-5 w-5" strokeWidth={2} /></span>
            <span className={`text-[17px] font-extrabold leading-tight tracking-tight ${light ? 'text-white' : 'text-slate-900'}`}>Bhatkal<span className="text-brand-600"> Mobile Hub</span></span>
        </Link>
    );
}

function NotificationBell() {
    const { user } = useAuth();
    if (!user) return null;
    return (
        <Link to="/notifications" className="relative rounded-xl p-2 text-slate-600 hover:bg-slate-100" aria-label={`Notifications (${user.unread_notifications} unread)`}>
            <Icon name="bell" />
            {user.unread_notifications > 0 && <span className="absolute right-1 top-1 flex h-4 min-w-4 items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-bold text-white">{user.unread_notifications}</span>}
        </Link>
    );
}

export function PublicLayout() {
    const { user } = useAuth();
    const meta = useMeta();
    const loc = useLocation();
    useEffect(() => { window.scrollTo(0, 0); }, [loc.pathname]);
    const nav = [['/', 'home', 'Home'], ['/search', 'search', 'Search'], ['/repairs', 'wrench', 'Repairs'], [user ? '/account' : '/login', 'user', user ? 'Account' : 'Sign in']];

    return (
        <div className="min-h-screen pb-20 md:pb-0">
            <header className="sticky top-0 z-30 border-b border-slate-200/70 bg-white/90 backdrop-blur">
                <div className="mx-auto flex h-16 max-w-6xl items-center justify-between gap-4 px-4">
                    <Logo />
                    <nav className="hidden items-center gap-1 text-sm font-semibold text-slate-600 md:flex">
                        {[['/search', 'Products'], ['/shops', 'Shops'], ['/repairs', 'Repairs']].map(([to, t]) => (
                            <NavLink key={to} to={to} className={({ isActive }) => `rounded-lg px-3 py-2 hover:bg-slate-100 ${isActive ? 'text-brand-700' : ''}`}>{t}</NavLink>
                        ))}
                    </nav>
                    <div className="flex items-center gap-1">
                        <NotificationBell />
                        {user ? (
                            <Link to={user.role === 'customer' ? '/account' : homeFor(user.role)} className="hidden items-center gap-2 rounded-xl px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100 md:flex"><Icon name="user" className="h-4 w-4" />{user.role === 'customer' ? user.name.split(' ')[0] : `${label(user.role)} portal`}</Link>
                        ) : (
                            <Link to="/login" className="hidden rounded-xl bg-brand-600 px-4 py-2 text-sm font-semibold text-white hover:bg-brand-700 md:block">Sign in</Link>
                        )}
                    </div>
                </div>
            </header>
            <main className="mx-auto max-w-6xl px-4 py-5"><Outlet /></main>
            <footer className="mt-10 hidden border-t border-slate-200 bg-white md:block">
                <div className="mx-auto grid max-w-6xl gap-6 px-4 py-8 text-sm text-slate-600 md:grid-cols-4">
                    <div className="md:col-span-2"><Logo /><p className="mt-3 max-w-sm">The local marketplace for mobile phones, accessories and repairs in {meta?.settings?.default_city || 'Bhatkal'}. Compare shops, check stock and book trusted technicians.</p></div>
                    <div className="space-y-2"><p className="font-semibold text-slate-900">For business</p><Link to="/login?role=merchant" className="block hover:text-brand-700">List your mobile shop</Link><Link to="/login?role=technician" className="block hover:text-brand-700">Join as a technician</Link><Link to="/staff" className="block hover:text-brand-700">Staff sign in</Link></div>
                    <div className="space-y-2"><p className="font-semibold text-slate-900">Help</p><Link to="/support" className="block hover:text-brand-700">Support & complaints</Link>{meta?.settings?.support_email && <a className="block hover:text-brand-700" href={`mailto:${meta.settings.support_email}`}>{meta.settings.support_email}</a>}</div>
                </div>
            </footer>
            <nav className="fixed inset-x-0 bottom-0 z-30 grid grid-cols-4 border-t border-slate-200 bg-white/95 pb-[env(safe-area-inset-bottom)] backdrop-blur md:hidden" aria-label="Main">
                {nav.map(([to, icon, t]) => (
                    <NavLink key={t} to={to} end={to === '/'} className={({ isActive }) => `flex flex-col items-center gap-0.5 py-2.5 text-[11px] font-semibold ${isActive ? 'text-brand-700' : 'text-slate-500'}`}>
                        <Icon name={icon} className="h-5 w-5" />{t}
                    </NavLink>
                ))}
            </nav>
        </div>
    );
}

export function RequireAuth({ roles, children }) {
    const { user, ready } = useAuth();
    const loc = useLocation();
    if (!ready) return <Loading />;
    if (!user) return <Navigate to={`${roles?.some((r) => ['admin', 'support'].includes(r)) ? '/staff' : '/login'}?next=${encodeURIComponent(loc.pathname + loc.search)}`} replace />;
    if (roles && !roles.includes(user.role)) return <Navigate to={homeFor(user.role)} replace />;
    return children || <Outlet />;
}

const PORTALS = {
    merchant: { title: 'Merchant', nav: [['', 'chart', 'Dashboard'], ['products', 'box', 'Products'], ['orders', 'bag', 'Orders'], ['enquiries', 'message', 'Enquiries'], ['reports', 'list', 'Reports'], ['settlements', 'rupee', 'Settlements'], ['shop', 'store', 'Shop profile']] },
    technician: { title: 'Technician', nav: [['', 'chart', 'Dashboard'], ['repairs', 'wrench', 'Repair jobs'], ['reports', 'list', 'Reports'], ['profile', 'user', 'Profile']] },
    admin: {
        title: 'Admin',
        nav: [['', 'chart', 'Dashboard'], ['shops', 'store', 'Shops'], ['technicians', 'wrench', 'Technicians'], ['users', 'user', 'Users'], ['products', 'box', 'Listings'], ['orders', 'bag', 'Orders'], ['repairs', 'tool', 'Repairs'],
            ['tickets', 'ticket', 'Complaints'], ['reviews', 'star', 'Reviews'], ['refunds', 'refresh', 'Refunds'], ['settlements', 'rupee', 'Settlements'], ['commissions', 'rupee', 'Commission'],
            ['catalogue', 'list', 'Catalogue'], ['templates', 'message', 'Templates'], ['reports', 'chart', 'Reports'], ['searches', 'search', 'Search insights'], ['logs', 'shield', 'Audit log'], ['system-logs', 'file', 'System logs'], ['settings', 'settings', 'Settings'], ['account', 'user', 'My account']],
        support: ['', 'tickets', 'orders', 'repairs', 'users', 'account'],
    },
};

export function PortalLayout({ portal }) {
    const { user, logout } = useAuth();
    const [open, setOpen] = useState(false);
    const loc = useLocation();
    const navigate = useNavigate();
    const cfg = PORTALS[portal];
    useEffect(() => { setOpen(false); }, [loc.pathname]);
    const items = cfg.nav.filter(([p]) => !(portal === 'admin' && user?.role === 'support') || cfg.support.includes(p));

    const sidebar = (
        <nav className="space-y-0.5 p-3" aria-label={`${cfg.title} navigation`}>
            {items.map(([path, icon, t]) => (
                <NavLink key={path} to={`/${portal}${path ? '/' + path : ''}`} end={!path}
                    className={({ isActive }) => `flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-semibold transition ${isActive ? 'bg-brand-50 text-brand-700' : 'text-slate-600 hover:bg-slate-100'}`}>
                    <Icon name={icon} className="h-[18px] w-[18px]" />{t}
                </NavLink>
            ))}
        </nav>
    );

    return (
        <div className="min-h-screen lg:flex">
            <aside className="hidden w-64 shrink-0 border-r border-slate-200 bg-white lg:block">
                <div className="sticky top-0 flex h-screen flex-col">
                    <div className="flex h-16 items-center border-b border-slate-100 px-4"><Logo /></div>
                    <div className="flex-1 overflow-y-auto">{sidebar}</div>
                    <div className="border-t border-slate-100 p-3">
                        <p className="px-3 text-sm font-semibold text-slate-900">{user?.name}</p>
                        <p className="px-3 text-xs text-slate-500">{label(user?.role)}</p>
                        <button onClick={async () => { await logout(); navigate('/'); }} className="mt-2 flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"><Icon name="logout" className="h-4 w-4" />Sign out</button>
                    </div>
                </div>
            </aside>
            {open && (
                <div className="fixed inset-0 z-40 bg-slate-900/40 lg:hidden" onClick={() => setOpen(false)}>
                    <div className="h-full w-72 overflow-y-auto bg-white" onClick={(e) => e.stopPropagation()}>
                        <div className="flex h-16 items-center border-b border-slate-100 px-4"><Logo /></div>
                        {sidebar}
                        <button onClick={async () => { await logout(); navigate('/'); }} className="mx-3 mb-6 flex items-center gap-3 rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100"><Icon name="logout" className="h-4 w-4" />Sign out</button>
                    </div>
                </div>
            )}
            <div className="min-w-0 flex-1">
                <header className="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white/90 px-4 backdrop-blur">
                    <div className="flex items-center gap-2">
                        <button className="rounded-xl p-2 text-slate-600 hover:bg-slate-100 lg:hidden" onClick={() => setOpen(true)} aria-label="Open menu"><Icon name="menu" /></button>
                        <Badge tone="teal">{cfg.title} portal</Badge>
                    </div>
                    <div className="flex items-center gap-1">
                        <Link to="/" className="rounded-xl px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-slate-100">View marketplace</Link>
                        <NotificationBell />
                    </div>
                </header>
                <main className="mx-auto max-w-6xl px-4 py-6"><Outlet /></main>
            </div>
        </div>
    );
}
