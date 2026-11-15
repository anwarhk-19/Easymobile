import { Route, Routes } from 'react-router-dom';
import { RequireAuth } from '../../components/layouts';
import NotFound from '../public/NotFound';
import { Account, Notifications } from './Account';
import { Enquiries, OrderDetail, Orders } from './Orders';
import { MyRepairs, NewRepair, RepairDetail } from './Repairs';
import { NewTicket, TicketDetail, Tickets } from './Support';

export default function CustomerRoutes() {
    return (
        <Routes>
            <Route element={<RequireAuth />}>
                <Route path="notifications" element={<Notifications />} />
                <Route path="support" element={<Tickets />} />
                <Route path="support/new" element={<NewTicket />} />
                <Route path="support/:id" element={<TicketDetail />} />
            </Route>
            <Route element={<RequireAuth roles={['customer']} />}>
                <Route path="account" element={<Account />} />
                <Route path="orders" element={<Orders />} />
                <Route path="orders/:id" element={<OrderDetail />} />
                <Route path="enquiries" element={<Enquiries />} />
                <Route path="my-repairs" element={<MyRepairs />} />
                <Route path="repairs/new" element={<NewRepair />} />
                <Route path="repairs/:id" element={<RepairDetail />} />
            </Route>
            <Route path="*" element={<NotFound />} />
        </Routes>
    );
}
