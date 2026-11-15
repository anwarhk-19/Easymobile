import { Link, useNavigate } from 'react-router-dom';
import Icon from '../../components/icons';
import { Async, Button, Empty, Field, Input, PageHeader, Section, Select, Textarea } from '../../components/ui';
import { post, put } from '../../lib/api';
import { useAuth } from '../../lib/auth';
import { useAction, useApi, useForm, useMeta } from '../../lib/hooks';
import { useToast } from '../../lib/toast';
import { ago } from '../../lib/format';

export function Account() {
    const { user, setUser, logout } = useAuth();
    const meta = useMeta();
    const toast = useToast();
    const navigate = useNavigate();
    const { values, field } = useForm({ name: user.name, email: user.email || '', area_id: user.profile?.area_id || '', address: user.profile?.address || '' });
    const save = useAction(() => put('/auth/profile', values), { onSuccess: (r) => { setUser(r.user); toast('Profile saved'); } });
    const links = [['/orders', 'bag', 'My orders', 'Track order requests and deliveries'], ['/my-repairs', 'wrench', 'My repairs', 'Quotes, progress and warranty'], ['/enquiries', 'message', 'My enquiries', 'Questions you sent to shops'], ['/support', 'ticket', 'Support & complaints', 'Raise or follow up an issue'], ['/notifications', 'bell', 'Notifications', `${user.unread_notifications} unread`]];

    return (
        <div className="mx-auto max-w-3xl">
            <PageHeader title={`Hi, ${user.name.split(' ')[0]}`} subtitle={`+91 ${user.phone}`} actions={<Button variant="secondary" onClick={async () => { await logout(); navigate('/'); }}><Icon name="logout" className="h-4 w-4" />Sign out</Button>} />
            <div className="grid gap-3 sm:grid-cols-2">
                {links.map(([to, icon, t, d]) => (
                    <Link key={to} to={to} className="card flex items-center gap-3 p-4 transition hover:border-brand-500">
                        <span className="rounded-xl bg-brand-50 p-2.5 text-brand-600"><Icon name={icon} /></span>
                        <span className="flex-1"><span className="block font-semibold text-slate-900">{t}</span><span className="text-xs text-slate-500">{d}</span></span>
                        <Icon name="chevron" className="h-4 w-4 text-slate-400" />
                    </Link>
                ))}
            </div>
            <Section title="Profile" className="mt-6">
                <form className="grid gap-4 sm:grid-cols-2" onSubmit={(e) => { e.preventDefault(); save.run(); }}>
                    <Field label="Name" error={save.errors.name}><Input {...field('name')} required /></Field>
                    <Field label="Email (optional)" error={save.errors.email}><Input type="email" {...field('email')} /></Field>
                    <Field label="Area"><Select placeholder="Select area" options={(meta?.areas || []).map((a) => ({ value: a.id, label: a.name }))} {...field('area_id')} /></Field>
                    <Field label="Address (used for deliveries)" className="sm:col-span-2"><Textarea rows={2} {...field('address')} /></Field>
                    <div><Button busy={save.busy}>Save profile</Button></div>
                </form>
            </Section>
        </div>
    );
}

export function Notifications() {
    const state = useApi('/notifications');
    const { refresh } = useAuth();
    const navigate = useNavigate();
    const readAll = useAction(() => post('/notifications/read-all'), { onSuccess: () => { state.reload(); refresh(); } });
    const open = async (n) => { if (!n.read_at) { await post(`/notifications/${n.id}/read`).catch(() => {}); refresh(); } if (n.data.link) navigate(n.data.link); else state.reload(); };
    return (
        <div className="mx-auto max-w-2xl">
            <PageHeader title="Notifications" actions={<Button variant="secondary" size="sm" busy={readAll.busy} onClick={() => readAll.run()}>Mark all read</Button>} />
            <Async state={state}>
                {(d) => d.data.length === 0 ? <Empty icon="bell" title="You’re all caught up">Order, repair and support updates will appear here.</Empty> : (
                    <ul className="card divide-y divide-slate-100">
                        {d.data.map((n) => (
                            <li key={n.id}><button onClick={() => open(n)} className={`flex w-full gap-3 px-4 py-3.5 text-left hover:bg-slate-50 ${n.read_at ? '' : 'bg-brand-50/40'}`}>
                                <span className={`mt-1.5 h-2 w-2 shrink-0 rounded-full ${n.read_at ? 'bg-transparent' : 'bg-brand-500'}`} />
                                <span className="flex-1"><span className="block text-sm font-semibold text-slate-900">{n.data.title}</span><span className="block text-sm text-slate-600">{n.data.body}</span><span className="text-xs text-slate-400">{ago(n.created_at)}</span></span>
                            </button></li>
                        ))}
                    </ul>
                )}
            </Async>
        </div>
    );
}
