import { useState } from 'react';
import { Link, useNavigate, useParams } from 'react-router-dom';
import DataTable, { Toolbar } from '../../components/DataTable';
import { ProductImage } from '../../components/cards';
import { Alert, Async, Badge, Button, ConfirmDialog, DL, Field, Input, Modal, PageHeader, Section, Select, Stat, StatusBadge, Tabs } from '../../components/ui';
import { openPrivate } from '../merchant/ShopForm';
import { post } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useAction, useApi, useDebounced } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { ago, date, dateTime, label, money, time12 } from '../../lib/format';

export function AdminDashboard() {
    const state = useApi('/admin/dashboard');
    const { user } = useAuth();
    return (
        <Async state={state}>
            {({ stats: s, queues: q, recent_orders }) => (
                <div className="space-y-5">
                    <PageHeader title="Marketplace overview" subtitle={`Signed in as ${user.name}`} />
                    {s.escalated_complaints > 0 && <Alert tone="red" title={`${s.escalated_complaints} escalated complaint(s) need attention`}><Link className="font-semibold underline" to="/admin/tickets?escalated=1">Open complaints</Link></Alert>}
                    <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                        <Stat label="Customers" value={s.customers} icon="user" to="/admin/users?role=customer" />
                        <Stat label="Merchants" value={s.merchants} icon="store" to="/admin/users?role=merchant" />
                        <Stat label="Active shops" value={s.active_shops} icon="store" tone="green" to="/admin/shops?status=approved" />
                        <Stat label="Pending shops" value={s.pending_shop_approvals} icon="clock" tone="amber" to="/admin/shops?status=pending" />
                        <Stat label="Technicians" value={s.technicians} icon="wrench" to="/admin/technicians" />
                        <Stat label="Pending technicians" value={s.pending_technician_approvals} icon="clock" tone="amber" to="/admin/technicians?status=pending" />
                        <Stat label="Products" value={s.products} icon="box" to="/admin/products" />
                        <Stat label="Active listings" value={s.active_listings} icon="check" tone="green" />
                        <Stat label="Enquiries" value={s.enquiries} icon="message" />
                        <Stat label="Orders" value={s.orders} icon="bag" to="/admin/orders" />
                        <Stat label="Completed orders" value={s.completed_orders} icon="check" tone="green" to="/admin/orders?status=completed" />
                        <Stat label="Cancelled orders" value={s.cancelled_orders} icon="x" tone="slate" to="/admin/orders?status=cancelled" />
                        <Stat label="Repair requests" value={s.repair_requests} icon="tool" to="/admin/repairs" />
                        <Stat label="Completed repairs" value={s.completed_repairs} icon="check" tone="green" />
                        <Stat label="Marketplace revenue" value={money(s.marketplace_revenue)} icon="rupee" tone="blue" />
                        <Stat label="Platform commission" value={money(s.platform_commission)} icon="rupee" tone="green" />
                        <Stat label="Pending settlements" value={money(s.pending_merchant_settlements)} icon="rupee" tone="amber" to="/admin/settlements" />
                        <Stat label="Open complaints" value={s.open_complaints} icon="ticket" tone="red" to="/admin/tickets" />
                    </div>
                    <div className="grid gap-4 lg:grid-cols-3">
                        <Section title="Shops awaiting verification">{q.shops.length ? <ul className="divide-y divide-slate-100 text-sm">{q.shops.map((x) => <li key={x.id}><Link className="flex justify-between py-2 hover:text-brand-700" to={`/admin/shops/${x.id}`}><b>{x.name}</b><span className="text-xs text-slate-400">{ago(x.created_at)}</span></Link></li>)}</ul> : <p className="text-sm text-slate-500">Queue is empty.</p>}</Section>
                        <Section title="Technicians awaiting review">{q.technicians.length ? <ul className="divide-y divide-slate-100 text-sm">{q.technicians.map((x) => <li key={x.id}><Link className="flex justify-between py-2 hover:text-brand-700" to={`/admin/technicians/${x.id}`}><b>{x.name}</b><span className="text-xs text-slate-400">{ago(x.created_at)}</span></Link></li>)}</ul> : <p className="text-sm text-slate-500">Queue is empty.</p>}</Section>
                        <Section title="Open complaints">{q.tickets.length ? <ul className="divide-y divide-slate-100 text-sm">{q.tickets.map((t) => <li key={t.id}><Link className="flex items-center justify-between gap-2 py-2 hover:text-brand-700" to={`/admin/tickets/${t.id}`}><span className="truncate"><b>{t.reference}</b> {t.subject}</span>{t.is_escalated ? <Badge tone="red">Escalated</Badge> : t.is_overdue ? <Badge tone="amber">Overdue</Badge> : <StatusBadge status={t.status} />}</Link></li>)}</ul> : <p className="text-sm text-slate-500">No open complaints.</p>}</Section>
                    </div>
                    <Section title="Recent orders">
                        <DataTable state={{ data: recent_orders }} columns={[{ key: 'reference', label: 'Order', render: (o) => <Link className="font-semibold text-brand-700" to={`/admin/orders/${o.id}`}>{o.reference}</Link> }, { key: 'shop', label: 'Shop', render: (o) => o.shop?.name }, { key: 'customer', label: 'Customer', render: (o) => o.customer?.name }, { key: 'total', label: 'Total', render: (o) => money(o.total_amount) }, { key: 'status', label: 'Status', render: (o) => <StatusBadge status={o.status} /> }, { key: 'at', label: 'Placed', render: (o) => ago(o.created_at) }]} />
                    </Section>
                </div>
            )}
        </Async>
    );
}

export function Verification({ kind }) {
    const navigate = useNavigate();
    const params = new URLSearchParams(window.location.search);
    const [status, setStatus] = useState(params.get('status') || 'pending');
    const [q, setQ] = useState('');
    const dq = useDebounced(q);
    const [page, setPage] = useState(1);
    const state = useApi(`/admin/${kind}`, { status, q: dq, page });
    const isShop = kind === 'shops';
    return (
        <div>
            <PageHeader title={isShop ? 'Shop verification' : 'Technician verification'} />
            <Tabs className="mb-3" value={status} onChange={(s) => { setStatus(s); setPage(1); }} tabs={[['pending', 'Pending'], ['approved', 'Approved'], ['rejected', 'Rejected'], ['suspended', 'Suspended'], ['', 'All']]} />
            <Toolbar><Input type="search" placeholder="Search name or phone" value={q} onChange={(e) => setQ(e.target.value)} /></Toolbar>
            <DataTable state={state} onPage={setPage} empty="Nothing in this queue" emptyIcon={isShop ? 'store' : 'wrench'} onRowClick={(r) => navigate(`/admin/${kind}/${r.id}`)} columns={[
                { key: 'name', label: 'Name', render: (r) => <><b>{r.name}</b>{r.is_featured && <Badge tone="amber" className="ml-1">Featured</Badge>}<span className="block text-xs text-slate-400">{isShop ? r.owner_name : r.shop_name}</span></> },
                { key: 'area', label: 'Area', render: (r) => r.area?.name }, { key: 'phone', label: 'Phone', render: (r) => r.phone },
                isShop ? { key: 'products_count', label: 'Products' } : { key: 'cats', label: 'Repairs', render: (r) => r.repair_categories?.length },
                { key: 'created_at', label: 'Registered', render: (r) => date(r.created_at) }, { key: 'status', label: 'Status', render: (r) => <StatusBadge status={r.status} /> },
            ]} />
        </div>
    );
}

export function VerificationDetail({ kind }) {
    const { id } = useParams();
    const toast = useToast();
    const isShop = kind === 'shops';
    const state = useApi(`/admin/${kind}/${id}`);
    const [dialog, setDialog] = useState(null);
    const act = useAction((action, reason) => post(`/admin/${kind}/${id}/status`, { action, reason }), { onSuccess: () => { setDialog(null); state.reload(); toast('Updated'); }, onError: (e) => toast(e.message, 'error') });
    return (
        <Async state={state}>
            {(d) => {
                const x = d.shop || d.technician;
                const owner = x.merchant || x.user;
                return (
                    <div className="space-y-4">
                        <PageHeader back={`/admin/${kind}`} title={x.name} subtitle={`Registered ${dateTime(x.created_at)}`} actions={<StatusBadge status={x.status} />} />
                        <div className="flex flex-wrap gap-2">
                            {['pending', 'rejected'].includes(x.status) && <Button onClick={() => setDialog('approve')}>Approve & publish</Button>}
                            {x.status === 'pending' && <Button variant="secondary" onClick={() => setDialog('reject')}>Reject</Button>}
                            {x.status === 'approved' && <Button variant="danger" onClick={() => setDialog('suspend')}>Suspend</Button>}
                            {x.status === 'suspended' && <Button onClick={() => setDialog('reinstate')}>Reinstate</Button>}
                            {isShop && x.status === 'approved' && <Button variant="secondary" busy={act.busy} onClick={() => act.run(x.is_featured ? 'unfeature' : 'feature')}>{x.is_featured ? 'Remove from featured' : 'Feature on homepage'}</Button>}
                            {x.status === 'approved' && <Button as={Link} variant="ghost" to={`/${isShop ? 'shops' : 'technicians'}/${x.slug}`}>Public page</Button>}
                        </div>
                        {x.rejection_reason && <Alert tone="red" title={x.status === 'suspended' ? 'Suspension reason' : 'Rejection reason'}>{x.rejection_reason}</Alert>}
                        <div className="grid gap-4 lg:grid-cols-2">
                            <Section title="Details">
                                <DL items={isShop ? [['Owner', x.owner_name], ['Phone', x.phone], ['WhatsApp', x.whatsapp], ['Address', `${x.address}, ${x.area?.name}`], ['Hours', `${time12(x.opens_at)} – ${time12(x.closes_at)}`], ['Pickup / delivery', `${x.pickup_available ? 'Pickup' : '—'} / ${x.delivery_available ? `Delivery ${money(x.delivery_fee)}` : '—'}`], ['Maps', x.maps_link ? <a key="m" className="text-brand-700" href={x.maps_link} target="_blank" rel="noreferrer">Open map</a> : '—'], ['Products / orders', `${x.products_count} / ${x.orders_count}`], ['Description', x.description]]
                                    : [['Shop', x.shop_name], ['Phone', x.phone], ['Address', `${x.address}, ${x.area?.name}`], ['Experience', `${x.experience_years} years`], ['Certifications', x.certifications], ['Service areas', x.service_areas?.map((a) => a.name).join(', ')], ['Brands', x.brands?.map((b) => b.name).join(', ')], ['Repairs', x.repair_categories?.map((c) => c.name).join(', ')], ['Warranty', `${x.default_warranty_days} days — ${x.warranty_terms}`], ['Modes', [x.pickup_available && 'Pickup', x.onsite_available && 'On-site'].filter(Boolean).join(', ') || 'Walk-in only']]} />
                            </Section>
                            <div className="space-y-4">
                                <Section title="Account owner"><DL items={[['Name', owner?.name], ['Phone', owner?.phone], ['Account status', <StatusBadge key="s" status={owner?.status} />]]} /><Button as={Link} size="sm" variant="secondary" className="mt-3" to={`/admin/users/${owner?.id}`}>Manage account</Button></Section>
                                <Section title="Verification documents">
                                    {x.documents?.length ? <ul className="space-y-2">{x.documents.map((doc) => <li key={doc.id}><button className="chip" onClick={() => openPrivate(`/api/v1/${isShop ? 'shop' : 'technician'}-documents/${doc.id}`)}>{label(doc.type)} · {doc.original_name}</button></li>)}</ul> : <p className="text-sm text-amber-700">No documents uploaded.</p>}
                                    <p className="mt-2 text-xs text-slate-500">Document views are recorded in the audit log.</p>
                                </Section>
                                {x.photos?.length > 0 && <Section title="Photos"><div className="flex flex-wrap gap-2">{x.photos.map((p) => <img key={p.id} src={p.url} alt="" className="h-20 w-20 rounded-xl object-cover" />)}</div></Section>}
                            </div>
                        </div>
                        <ConfirmDialog open={dialog === 'approve'} onClose={() => setDialog(null)} busy={act.busy} title="Approve and publish?" message="It will become visible to customers immediately." confirmText="Approve" onConfirm={() => act.run('approve')} />
                        <ConfirmDialog open={dialog === 'reinstate'} onClose={() => setDialog(null)} busy={act.busy} title="Reinstate?" confirmText="Reinstate" onConfirm={() => act.run('reinstate')} />
                        <ConfirmDialog open={dialog === 'reject'} onClose={() => setDialog(null)} busy={act.busy} tone="danger" reason reasonLabel="What must be fixed? (shown to the applicant)" title="Reject application" confirmText="Reject" onConfirm={(r) => act.run('reject', r)} />
                        <ConfirmDialog open={dialog === 'suspend'} onClose={() => setDialog(null)} busy={act.busy} tone="danger" reason title="Suspend" message="It will be hidden from customers. Open orders/jobs are not cancelled automatically." confirmText="Suspend" onConfirm={(r) => act.run('suspend', r)} />
                    </div>
                );
            }}
        </Async>
    );
}

export function Users() {
    const navigate = useNavigate();
    const { user } = useAuth();
    const toast = useToast();
    const [role, setRole] = useState(new URLSearchParams(window.location.search).get('role') || '');
    const [status, setStatus] = useState('');
    const [q, setQ] = useState('');
    const dq = useDebounced(q);
    const [page, setPage] = useState(1);
    const [staff, setStaff] = useState(null);
    const state = useApi('/admin/users', { role, status, q: dq, page });
    const create = useAction(() => post('/admin/users/staff', staff), { onSuccess: () => { toast('Staff account created'); setStaff(null); state.reload(); }, onError: (e) => toast(e.message, 'error') });
    return (
        <div>
            <PageHeader title="Users" actions={user.role === 'admin' && <Button onClick={() => setStaff({ name: '', email: '', role: 'support', password: '' })}>Add staff</Button>} />
            <Tabs className="mb-3" value={role} onChange={(r) => { setRole(r); setPage(1); }} tabs={[['', 'All'], ['customer', 'Customers'], ['merchant', 'Merchants'], ['technician', 'Technicians'], ['support', 'Support'], ['admin', 'Admins']]} />
            <Toolbar><Input type="search" placeholder="Name, phone or email" value={q} onChange={(e) => setQ(e.target.value)} /><Select value={status} onChange={(e) => setStatus(e.target.value)} placeholder="Any status" options={['active', 'suspended']} /></Toolbar>
            <DataTable state={state} onPage={setPage} empty="No users" emptyIcon="user" onRowClick={(u) => navigate(`/admin/users/${u.id}`)} columns={[
                { key: 'name', label: 'Name', render: (u) => <b>{u.name}</b> }, { key: 'contact', label: 'Contact', render: (u) => u.phone || u.email }, { key: 'role', label: 'Role', render: (u) => label(u.role) },
                { key: 'orders_count', label: 'Orders' }, { key: 'repair_requests_count', label: 'Repairs' }, { key: 'created_at', label: 'Joined', render: (u) => date(u.created_at) }, { key: 'status', label: 'Status', render: (u) => <StatusBadge status={u.status} /> },
            ]} />
            <Modal open={!!staff} onClose={() => setStaff(null)} title="Add staff account" footer={<Button busy={create.busy} onClick={() => create.run()}>Create</Button>}>
                {staff && <div className="space-y-3">
                    <Field label="Name" error={create.errors.name}><Input value={staff.name} onChange={(e) => setStaff({ ...staff, name: e.target.value })} /></Field>
                    <Field label="Email" error={create.errors.email}><Input type="email" value={staff.email} onChange={(e) => setStaff({ ...staff, email: e.target.value })} /></Field>
                    <Field label="Role"><Select value={staff.role} onChange={(e) => setStaff({ ...staff, role: e.target.value })} options={[{ value: 'support', label: 'Support operator' }, { value: 'admin', label: 'Admin' }]} /></Field>
                    <Field label="Temporary password" error={create.errors.password} hint="At least 10 characters with upper/lower case and a number"><Input type="password" value={staff.password} onChange={(e) => setStaff({ ...staff, password: e.target.value })} /></Field>
                </div>}
            </Modal>
        </div>
    );
}

export function UserDetail() {
    const { id } = useParams();
    const toast = useToast();
    const { user: me } = useAuth();
    const state = useApi(`/admin/users/${id}`);
    const [dialog, setDialog] = useState(false);
    const act = useAction((path, body) => post(`/admin/users/${id}/${path}`, body), { onSuccess: () => { setDialog(false); state.reload(); toast('Account updated'); }, onError: (e) => toast(e.message, 'error') });
    return (
        <Async state={state}>
            {({ user: u, recent_orders, recent_repairs }) => (
                <div className="space-y-4">
                    <PageHeader back="/admin/users" title={u.name} subtitle={`${label(u.role)} · joined ${date(u.created_at)}`} actions={<><StatusBadge status={u.status} />{me.role === 'admin' && u.id !== me.id && (u.status === 'active' ? <Button variant="danger" onClick={() => setDialog(true)}>Suspend</Button> : <Button busy={act.busy} onClick={() => act.run('activate')}>Reactivate</Button>)}</>} />
                    {u.suspension_reason && <Alert tone="red" title="Suspended">{u.suspension_reason}</Alert>}
                    <Section title="Account"><DL items={[['Phone', u.phone], ['Email', u.email], ['Last login', dateTime(u.last_login_at)], ['Orders', u.orders_count], ['Repairs', u.repair_requests_count], u.shop && ['Shop', <Link key="s" className="text-brand-700" to={`/admin/shops/${u.shop.id}`}>{u.shop.name}</Link>], u.technician_profile && ['Technician profile', <Link key="t" className="text-brand-700" to={`/admin/technicians/${u.technician_profile.id}`}>{u.technician_profile.name}</Link>]]} /></Section>
                    {recent_orders.length > 0 && <Section title="Recent orders"><DataTable state={{ data: recent_orders }} columns={[{ key: 'r', label: 'Order', render: (o) => <Link className="text-brand-700" to={`/admin/orders/${o.id}`}>{o.reference}</Link> }, { key: 's', label: 'Shop', render: (o) => o.shop?.name }, { key: 't', label: 'Total', render: (o) => money(o.total_amount) }, { key: 'st', label: 'Status', render: (o) => <StatusBadge status={o.status} /> }]} /></Section>}
                    {recent_repairs.length > 0 && <Section title="Recent repairs"><DataTable state={{ data: recent_repairs }} columns={[{ key: 'r', label: 'Repair', render: (o) => <Link className="text-brand-700" to={`/admin/repairs/${o.id}`}>{o.reference}</Link> }, { key: 'st', label: 'Status', render: (o) => <StatusBadge status={o.status} /> }, { key: 'c', label: 'Created', render: (o) => date(o.created_at) }]} /></Section>}
                    <ConfirmDialog open={dialog} onClose={() => setDialog(false)} busy={act.busy} tone="danger" reason title="Suspend account" message="The user is signed out everywhere and cannot sign in until reactivated." confirmText="Suspend" onConfirm={(reason) => act.run('suspend', { reason })} />
                </div>
            )}
        </Async>
    );
}

export function Listings() {
    const toast = useToast();
    const [status, setStatus] = useState('');
    const [q, setQ] = useState('');
    const dq = useDebounced(q);
    const [page, setPage] = useState(1);
    const [target, setTarget] = useState(null);
    const state = useApi('/admin/products', { status, q: dq, page });
    const act = useAction((p, action, reason) => post(`/admin/products/${p.id}/moderate`, { action, reason }), { onSuccess: () => { setTarget(null); state.reload(); toast('Listing updated'); }, onError: (e) => toast(e.message, 'error') });
    return (
        <div>
            <PageHeader title="Listing moderation" />
            <Tabs className="mb-3" value={status} onChange={(s) => { setStatus(s); setPage(1); }} tabs={[['', 'All'], ['active', 'Active'], ['inactive', 'Inactive'], ['suspended', 'Suspended']]} />
            <Toolbar><Input type="search" placeholder="Search name or SKU" value={q} onChange={(e) => setQ(e.target.value)} /></Toolbar>
            <DataTable state={state} onPage={setPage} empty="No listings" emptyIcon="box" columns={[
                { key: 'name', label: 'Product', render: (p) => <div className="flex min-w-56 items-center gap-3"><div className="h-10 w-10 shrink-0 overflow-hidden rounded-lg"><ProductImage product={p} className="h-10" /></div><div><b>{p.name}</b><span className="block text-xs text-slate-400">{p.sku} · {p.category?.name}</span></div></div> },
                { key: 'shop', label: 'Shop', render: (p) => p.shop?.name }, { key: 'price', label: 'Price', render: (p) => money(p.effective_price) }, { key: 'stock', label: 'Stock', render: (p) => p.stock_quantity },
                { key: 'status', label: 'Status', render: (p) => (p.is_suspended ? <Badge tone="red">Suspended</Badge> : <StatusBadge status={p.status} />) },
                { key: 'a', label: '', render: (p) => (p.is_suspended ? <Button size="sm" variant="secondary" onClick={() => act.run(p, 'reinstate')}>Reinstate</Button> : <Button size="sm" variant="ghost" onClick={() => setTarget(p)}>Suspend</Button>) },
            ]} />
            <ConfirmDialog open={!!target} onClose={() => setTarget(null)} busy={act.busy} tone="danger" reason reasonLabel="Reason (shown to the merchant)" title={`Suspend “${target?.name}”`} confirmText="Suspend listing" onConfirm={(r) => act.run(target, 'suspend', r)} />
        </div>
    );
}
