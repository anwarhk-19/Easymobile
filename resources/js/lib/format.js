export const money = (v) => (v === null || v === undefined || v === '' ? '—' : '₹' + Number(v).toLocaleString('en-IN', { maximumFractionDigits: 2 }));
export const date = (v) => (v ? new Date(v).toLocaleDateString('en-IN', { day: 'numeric', month: 'short', year: 'numeric' }) : '—');
export const dateTime = (v) => (v ? new Date(v).toLocaleString('en-IN', { day: 'numeric', month: 'short', hour: 'numeric', minute: '2-digit' }) : '—');
export const label = (s) => (s ? String(s).replace(/_/g, ' ').replace(/\b\w/g, (c) => c.toUpperCase()) : '');
export const time12 = (t) => {
    if (!t) return '';
    const [h, m] = t.split(':').map(Number);
    return `${((h + 11) % 12) + 1}:${String(m).padStart(2, '0')} ${h < 12 ? 'AM' : 'PM'}`;
};
export const ago = (v) => {
    if (!v) return '';
    const s = Math.round((Date.now() - new Date(v).getTime()) / 1000);
    if (s < 60) return 'just now';
    if (s < 3600) return `${Math.floor(s / 60)} min ago`;
    if (s < 86400) return `${Math.floor(s / 3600)} h ago`;
    return `${Math.floor(s / 86400)} d ago`;
};
export const minutes = (m) => (!m ? '—' : m < 60 ? `${Math.round(m)} min` : m < 1440 ? `${(m / 60).toFixed(1)} h` : `${(m / 1440).toFixed(1)} days`);

/** Status → [label, tone] used by StatusBadge. */
export const STATUS = {
    requested: ['Awaiting shop', 'amber'], quoted: ['Quote ready', 'blue'], accepted: ['Quote accepted', 'blue'], confirmed: ['Confirmed', 'teal'],
    ready: ['Ready', 'teal'], handed_over: ['Delivered / collected', 'teal'], completed: ['Completed', 'green'], cancelled: ['Cancelled', 'slate'], rejected: ['Declined', 'red'],
    submitted: ['Submitted', 'amber'], diagnosing: ['Diagnosing', 'blue'], quote_rejected: ['Quote rejected', 'red'], approved: ['Approved', 'green'],
    in_progress: ['In repair', 'teal'], testing: ['Testing', 'teal'], ready_for_handover: ['Ready for pickup', 'green'],
    pending: ['Pending', 'amber'], suspended: ['Suspended', 'red'], active: ['Active', 'green'], inactive: ['Inactive', 'slate'],
    new: ['New', 'amber'], responded: ['Replied', 'blue'], converted: ['Order placed', 'green'], closed: ['Closed', 'slate'],
    open: ['Open', 'amber'], acknowledged: ['Acknowledged', 'blue'], escalated: ['Escalated', 'red'], resolved: ['Resolved', 'green'],
    paid: ['Paid', 'green'], processing: ['Processing', 'blue'], on_hold: ['On hold', 'slate'], processed: ['Processed', 'green'], superseded: ['Superseded', 'slate'],
    partially_refunded: ['Part refunded', 'blue'], refunded: ['Refunded', 'slate'],
    in_stock: ['In stock', 'green'], limited: ['Few left', 'amber'], out_of_stock: ['Out of stock', 'red'], on_order: ['On order', 'blue'],
};

export const whatsappIcon = 'M17.5 14.4c-.3-.1-1.7-.8-2-.9-.3-.1-.5-.1-.7.1-.2.3-.8.9-.9 1.1-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.3-1.4-.9-.8-1.4-1.7-1.6-2-.2-.3 0-.5.1-.6l.4-.5c.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5h-.6c-.2 0-.5.1-.8.4-.3.3-1 1-1 2.4s1.1 2.8 1.2 3c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.7-.7 2-1.4.2-.7.2-1.2.2-1.4-.1-.1-.3-.2-.6-.3zM12 21.8c-1.8 0-3.5-.5-5-1.4l-.4-.2-3.7 1 1-3.6-.2-.4C2.7 15.6 2.2 13.8 2.2 12 2.2 6.6 6.6 2.2 12 2.2c2.6 0 5.1 1 6.9 2.9 1.8 1.8 2.9 4.3 2.9 6.9 0 5.4-4.4 9.8-9.8 9.8zm8.4-18.2C18.2 1.3 15.2.1 12 .1 5.4.1.1 5.4.1 12c0 2.1.5 4.1 1.6 5.9L0 24l6.3-1.7c1.7.9 3.7 1.4 5.7 1.4 6.6 0 11.9-5.3 11.9-11.9 0-3.2-1.2-6.2-3.5-8.4z';
