<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Conversation;
use App\Models\Event;
use App\Models\EventImage;
use App\Models\Like;
use App\Models\Message;
use App\Models\Payment;
use App\Models\Profile;
use App\Models\Report;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserMatch;
use App\Models\UserNotification;
use App\Models\VerificationRequest;
use App\Services\CloudinaryService;
use App\Services\ProfileCertificationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminController extends ApiController
{
    public function overview(Request $request)
    {
        $this->authorizeAdmin($request);

        return $this->ok([
            'kpis' => [
                'users' => User::query()->count(),
                'active_users' => User::query()->where('status', 'active')->count(),
                'suspended_users' => User::query()->where('status', 'suspended')->count(),
                'profiles' => Profile::query()->count(),
                'complete_profiles' => Profile::query()->where('completion_score', '>=', 80)->count(),
                'pending_verifications' => VerificationRequest::query()->where('status', 'pending')->count(),
                'pending_certifications' => Profile::query()->where('certification_status', 'eligible')->count(),
                'open_reports' => Report::query()->where('status', 'open')->count(),
                'matches' => UserMatch::query()->count(),
                'conversations' => Conversation::query()->count(),
                'messages' => Message::query()->count(),
                'events' => Event::query()->count(),
                'support_tickets' => SupportTicket::query()->count(),
                'payments_total' => Payment::query()->count(),
                'revenue_cents' => Payment::query()->where('status', 'confirmed')->sum('amount_cents'),
                'active_subscriptions' => Subscription::query()
                    ->where('status', 'active')
                    ->where(fn (Builder $query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                    ->count(),
            ],
            'series' => [
                'users' => $this->dailySeries(User::query(), 'created_at'),
                'profiles' => $this->dailySeries(Profile::query(), 'created_at'),
                'matches' => $this->dailySeries(UserMatch::query(), 'matched_at'),
                'messages' => $this->dailySeries(Message::query(), 'created_at'),
                'payments' => $this->dailySeries(Payment::query()->where('status', 'confirmed'), 'confirmed_at'),
                'reports' => $this->dailySeries(Report::query(), 'created_at'),
                'events' => $this->dailySeries(Event::query(), 'created_at'),
                'support' => $this->dailySeries(SupportTicket::query(), 'created_at'),
            ],
            'breakdowns' => [
                'users_by_status' => $this->countsBy(User::query(), 'status'),
                'profiles_by_gender' => $this->countsBy(Profile::query(), 'gender'),
                'reports_by_status' => $this->countsBy(Report::query(), 'status'),
                'payments_by_status' => $this->countsBy(Payment::query(), 'status'),
                'messages_by_type' => $this->countsBy(Message::query(), 'type'),
                'events_by_status' => $this->countsBy(Event::query(), 'status'),
                'support_by_status' => $this->countsBy(SupportTicket::query(), 'status'),
            ],
            'latest' => [
                'users' => $this->usersQuery()->limit(6)->get()->map(fn (User $user) => $this->userRow($user)),
                'reports' => $this->reportsQuery()->limit(6)->get()->map(fn (Report $report) => $this->reportRow($report)),
                'payments' => $this->paymentsQuery()->limit(6)->get()->map(fn (Payment $payment) => $this->paymentRow($payment)),
                'support' => $this->supportTicketsQuery()->limit(6)->get()->map(fn (SupportTicket $ticket) => $this->supportTicketRow($ticket)),
            ],
            'system' => [
                'api' => 'ok',
                'database' => $this->databaseStatus(),
                'environment' => app()->environment(),
                'server_time' => now()->toIso8601String(),
                'online_users' => User::query()->where('last_seen_at', '>=', now()->subMinutes(5))->count(),
                'pending_actions' => [
                    'verifications' => VerificationRequest::query()->where('status', 'pending')->count(),
                    'certifications' => Profile::query()->where('certification_status', 'eligible')->count(),
                    'reports' => Report::query()->whereIn('status', ['open', 'reviewing'])->count(),
                    'payments' => Payment::query()->where('status', 'pending')->count(),
                    'support' => SupportTicket::query()->whereIn('status', ['open', 'in_progress', 'waiting_user'])->count(),
                ],
            ],
        ]);
    }

    public function events(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = Event::query()
            ->with('images')
            ->withCount([
                'invitations',
                'invitations as accepted_count' => fn (Builder $query) => $query->where('status', 'accepted'),
                'invitations as pending_count' => fn (Builder $query) => $query->where('status', 'pending'),
            ])
            ->when($request->query('status'), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->query('category'), fn (Builder $query, string $category) => $query->where('category', $category))
            ->orderByRaw('starts_at is null')
            ->orderBy('starts_at')
            ->latest();

        return $this->ok($query->paginate((int) $request->query('per_page', 20))->through(fn (Event $event) => $this->eventRow($event)));
    }

    public function storeEvent(Request $request, CloudinaryService $cloudinary)
    {
        $admin = $this->authorizeAdmin($request);
        $data = $this->validateEvent($request);
        unset($data['cover_image'], $data['images']);

        $event = Event::query()->create($data);
        $this->syncEventImages($request, $event, $cloudinary);
        $this->audit($admin, 'admin.event_created', $event, null, ['status' => $event->status]);

        return $this->ok($this->eventRow($event->load('images')->loadCount(['invitations'])), 'Evenement cree.', status: 201);
    }

    public function updateEvent(Request $request, Event $event, CloudinaryService $cloudinary)
    {
        $admin = $this->authorizeAdmin($request);
        $data = $this->validateEvent($request, partial: true);
        unset($data['cover_image'], $data['images']);

        $event->update($data);
        $this->syncEventImages($request, $event, $cloudinary);
        $this->audit($admin, 'admin.event_updated', $event, null, ['status' => $event->status]);

        return $this->ok($this->eventRow($event->refresh()->load('images')->loadCount(['invitations'])), 'Evenement mis a jour.');
    }

    public function users(Request $request)
    {
        $this->authorizeAdmin($request);
        $query = $this->usersQuery()
            ->when($request->query('q'), function (Builder $query, string $search) {
                $query->where(fn (Builder $inner) => $inner
                    ->where('email', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%")
                    ->orWhereHas('profile', fn (Builder $profile) => $profile->where('first_name', 'like', "%{$search}%")));
            })
            ->when($request->query('status'), fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest();

        return $this->ok($query->paginate((int) $request->query('per_page', 20))->through(fn (User $user) => $this->userRow($user)));
    }

    public function user(Request $request, User $user)
    {
        $this->authorizeAdmin($request);

        $user->load([
            'profile.university',
            'profile.interests',
            'photos',
            'subscriptions.plan',
            'verificationRequests',
        ]);

        return $this->ok([
            ...$this->userRow($user),
            'profile' => $user->profile,
            'photos' => $user->photos,
            'subscriptions' => $user->subscriptions,
            'verifications' => $user->verificationRequests,
            'activity' => [
                'likes_sent' => Like::query()->where('sender_id', $user->id)->count(),
                'likes_received' => Like::query()->where('receiver_id', $user->id)->count(),
                'matches' => UserMatch::query()->where(fn (Builder $q) => $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id))->count(),
                'messages_sent' => Message::query()->where('sender_id', $user->id)->count(),
                'reports_received' => Report::query()->where('reported_user_id', $user->id)->count(),
                'payments_cents' => Payment::query()->where('user_id', $user->id)->where('status', 'confirmed')->sum('amount_cents'),
            ],
        ]);
    }

    public function updateUserStatus(Request $request, User $user)
    {
        $admin = $this->authorizeAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'paused', 'suspended', 'banned'])],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $user->update(['status' => $data['status']]);
        $message = match ($data['status']) {
            'active' => 'Votre compte US a ete reactive.',
            'paused' => 'Votre compte US a ete mis en pause par la moderation.',
            'suspended' => 'Votre compte US est suspendu temporairement.',
            default => 'Votre compte US a ete bloque par la moderation.',
        };

        UserNotification::query()->create([
            'user_id' => $user->id,
            'category' => 'moderation',
            'title' => 'Mise a jour du compte',
            'body' => trim($message.' '.($data['reason'] ?? '')),
            'data' => ['url' => '/dashboard/settings', 'status' => $data['status']],
        ]);

        $this->audit($admin, 'admin.user_status_updated', $user, $data['reason'] ?? null, ['status' => $data['status']]);

        return $this->ok($this->userRow($user->refresh()), 'Statut utilisateur mis a jour.');
    }

    public function reports(Request $request)
    {
        $this->authorizeAdmin($request);
        $query = $this->reportsQuery()
            ->when($request->query('status'), fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($request->query('priority'), fn (Builder $query, string $priority) => $query->where('priority', $priority))
            ->latest();

        return $this->ok($query->paginate((int) $request->query('per_page', 20))->through(fn (Report $report) => $this->reportRow($report)));
    }

    public function updateReport(Request $request, Report $report)
    {
        $admin = $this->authorizeAdmin($request);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'reviewing', 'resolved', 'dismissed'])],
            'priority' => ['nullable', 'integer', 'min:1', 'max:3'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $report->update([
            'status' => $data['status'],
            'priority' => $data['priority'] ?? $report->priority,
        ]);

        $this->audit($admin, 'admin.report_updated', $report, $data['reason'] ?? null, ['status' => $data['status']]);

        return $this->ok($this->reportRow($report->refresh()), 'Signalement mis a jour.');
    }

    public function payments(Request $request)
    {
        $this->authorizeAdmin($request);
        $query = $this->paymentsQuery()
            ->when($request->query('status'), fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest();

        return $this->ok($query->paginate((int) $request->query('per_page', 20))->through(fn (Payment $payment) => $this->paymentRow($payment)));
    }

    public function messages(Request $request)
    {
        $this->authorizeAdmin($request);
        $query = Message::query()
            ->with('sender.profile')
            ->withCount('reports')
            ->when($request->query('type'), fn (Builder $query, string $type) => $query->where('type', $type))
            ->latest();

        return $this->ok($query->paginate((int) $request->query('per_page', 30))->through(fn (Message $message) => [
            'id' => $message->id,
            'conversation_id' => $message->conversation_id,
            'sender_id' => $message->sender_id,
            'sender' => $message->sender?->profile?->first_name ?? $message->sender?->name ?? $message->sender?->email,
            'type' => $message->type,
            'body' => str($message->body ?? $message->sticker_code ?? $message->attachment_url ?? '')->limit(180)->toString(),
            'read_at' => $message->read_at,
            'reported_at' => $message->reported_at,
            'reports_count' => $message->reports_count,
            'created_at' => $message->created_at,
        ]));
    }

    public function verifications(Request $request)
    {
        $this->authorizeAdmin($request);
        $query = VerificationRequest::query()
            ->with('user.profile')
            ->when($request->query('status'), fn (Builder $query, string $status) => $query->where('status', $status))
            ->latest();

        return $this->ok($query->paginate((int) $request->query('per_page', 20))->through(fn (VerificationRequest $item) => [
            'id' => $item->id,
            'user_id' => $item->user_id,
            'user' => $item->user?->profile?->first_name ?? $item->user?->name ?? $item->user?->email,
            'type' => $item->type,
            'status' => $item->status,
            'image_url' => $item->image_url,
            'rejection_reason' => $item->rejection_reason,
            'reviewed_at' => $item->reviewed_at,
            'created_at' => $item->created_at,
        ]));
    }

    public function supportTickets(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = $this->supportTicketsQuery();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->ok($query->paginate((int) $request->query('per_page', 20))->through(fn (SupportTicket $ticket) => $this->supportTicketRow($ticket)));
    }

    public function updateSupportTicket(Request $request, SupportTicket $ticket)
    {
        $admin = $this->authorizeAdmin($request);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['open', 'in_progress', 'waiting_user', 'resolved', 'closed'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'admin_note' => ['nullable', 'string', 'max:4000'],
        ]);

        if (($data['status'] ?? null) === 'resolved') {
            $data['resolved_at'] = now();
        }

        $ticket->update([...$data, 'handled_by' => $admin->id]);
        UserNotification::query()->create([
            'user_id' => $ticket->user_id,
            'category' => 'support',
            'title' => 'Support mis a jour',
            'body' => "Votre ticket {$ticket->subject} est maintenant {$ticket->status}.",
            'data' => ['url' => '/dashboard/support/contact', 'ticket_id' => $ticket->id],
        ]);
        $this->audit($admin, 'admin.support_ticket_updated', $ticket, $data['admin_note'] ?? null, ['status' => $ticket->status]);

        return $this->ok($this->supportTicketRow($ticket->refresh()->load('user.profile')), 'Ticket support mis a jour.');
    }

    public function updateVerification(Request $request, VerificationRequest $verification, ProfileCertificationService $certification)
    {
        $admin = $this->authorizeAdmin($request);
        abort_if($verification->status === 'approved', 409, 'Verification deja approuvee et verrouillee.');
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected'])],
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $verification->update([
            'status' => $data['status'],
            'rejection_reason' => $data['status'] === 'rejected' ? ($data['rejection_reason'] ?? null) : null,
            'reviewed_at' => now(),
        ]);

        UserNotification::query()->create([
            'user_id' => $verification->user_id,
            'category' => 'verification',
            'title' => $data['status'] === 'approved' ? 'Verification approuvee' : 'Verification refusee',
            'body' => $data['status'] === 'approved' ? 'Votre profil gagne en confiance.' : ($data['rejection_reason'] ?? 'Veuillez renvoyer une photo plus claire.'),
            'data' => ['url' => '/dashboard/verification'],
        ]);

        $this->audit($admin, 'admin.verification_updated', $verification, $data['rejection_reason'] ?? null, ['status' => $data['status']]);
        $certification->refresh($verification->user()->firstOrFail());

        return $this->ok($verification->fresh(), 'Verification mise a jour.');
    }

    public function certifications(Request $request)
    {
        $this->authorizeAdmin($request);

        $query = $this->usersQuery()
            ->whereHas('profile', fn (Builder $profile) => $profile->whereIn('certification_status', ['eligible', 'certified']))
            ->latest();

        return $this->ok($query->paginate((int) $request->query('per_page', 20))->through(fn (User $user) => $this->userRow($user)));
    }

    public function certify(Request $request, User $user, ProfileCertificationService $certification)
    {
        $admin = $this->authorizeAdmin($request);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $profile = $certification->certify($user);
        UserNotification::query()->create([
            'user_id' => $user->id,
            'category' => 'verification',
            'title' => 'Profil certifie',
            'body' => 'Votre profil US est maintenant certifie avec le badge dore.',
            'data' => ['url' => '/dashboard/profile'],
        ]);
        $this->audit($admin, 'admin.user_certified', $profile, $data['reason'] ?? null, ['user_id' => $user->id]);

        return $this->ok($this->userRow($user->refresh()->load(['profile.university', 'profile.interests', 'photos'])), 'Profil certifie.');
    }

    private function usersQuery(): Builder
    {
        return User::query()
            ->with(['profile.university', 'profile.interests', 'photos'])
            ->withCount([
                'photos',
                'verificationRequests as approved_verifications_count' => fn (Builder $q) => $q->where('status', 'approved'),
                'verificationRequests as pending_verifications_count' => fn (Builder $q) => $q->where('status', 'pending'),
            ]);
    }

    private function reportsQuery(): Builder
    {
        return Report::query()->with(['reportable', 'reporter.profile', 'reportedUser.profile']);
    }

    private function paymentsQuery(): Builder
    {
        return Payment::query()->with(['plan', 'user.profile']);
    }

    private function supportTicketsQuery(): Builder
    {
        return SupportTicket::query()->with(['user.profile'])->latest();
    }

    private function userRow(User $user): array
    {
        $profile = $user->profile;
        $interactions = Like::query()->where('sender_id', $user->id)->orWhere('receiver_id', $user->id)->count()
            + Message::query()->where('sender_id', $user->id)->count()
            + UserMatch::query()->where(fn (Builder $q) => $q->where('user_one_id', $user->id)->orWhere('user_two_id', $user->id))->count();
        $reports = Report::query()->where('reported_user_id', $user->id)->count();
        $certificationScore = $profile?->certification_score ?: min(100, (int) (($profile?->completion_score ?? 0) * 0.45)
            + min(25, ($user->approved_verifications_count ?? 0) * 25)
            + min(20, $interactions * 2)
            + min(10, ($user->photos_count ?? $user->photos()->count()) * 2)
            - min(30, $reports * 10));

        return [
            'id' => $user->id,
            'name' => $profile?->first_name ?? $user->name,
            'email' => $user->email,
            'status' => $user->status,
            'email_verified_at' => $user->email_verified_at,
            'last_seen_at' => $user->last_seen_at,
            'created_at' => $user->created_at,
            'profile_completion' => $profile?->completion_score ?? 0,
            'certification_score' => max(0, $certificationScore),
            'certification_status' => $profile?->certification_status ?? 'not_eligible',
            'certified_at' => $profile?->certified_at,
            'gender' => $profile?->gender,
            'university' => $profile?->university?->name,
            'photos_count' => $user->photos_count ?? $user->photos()->count(),
            'reports_count' => $reports,
            'pending_verifications_count' => $user->pending_verifications_count ?? 0,
        ];
    }

    private function reportRow(Report $report): array
    {
        return [
            'id' => $report->id,
            'category' => $report->category,
            'status' => $report->status,
            'priority' => $report->priority,
            'details' => $report->details,
            'reporter' => $report->reporter?->profile?->first_name ?? $report->reporter?->email,
            'reported_user_id' => $report->reported_user_id,
            'reported_user' => $report->reportedUser?->profile?->first_name ?? $report->reportedUser?->email,
            'target_type' => class_basename($report->reportable_type),
            'target_id' => $report->reportable_id,
            'created_at' => $report->created_at,
        ];
    }

    private function paymentRow(Payment $payment): array
    {
        return [
            'id' => $payment->id,
            'user_id' => $payment->user_id,
            'user' => $payment->user?->profile?->first_name ?? $payment->user?->email,
            'plan' => $payment->plan?->name,
            'amount_cents' => $payment->amount_cents,
            'currency' => $payment->currency,
            'provider' => $payment->provider,
            'phone' => $payment->phone,
            'status' => $payment->status,
            'confirmed_at' => $payment->confirmed_at,
            'created_at' => $payment->created_at,
        ];
    }

    private function eventRow(Event $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'description' => $event->description,
            'category' => $event->category,
            'status' => $event->status,
            'venue' => $event->venue,
            'city' => $event->city,
            'starts_at' => $event->starts_at,
            'capacity' => $event->capacity,
            'cover_url' => $event->cover_url,
            'is_premium' => $event->is_premium,
            'images' => $event->relationLoaded('images') ? $event->images->values()->map(fn (EventImage $image) => [
                'id' => $image->id,
                'url' => $image->url,
                'sort_order' => $image->sort_order,
                'is_cover' => $image->is_cover,
            ]) : [],
            'invitations_count' => $event->invitations_count ?? 0,
            'accepted_count' => $event->accepted_count ?? 0,
            'pending_count' => $event->pending_count ?? 0,
            'created_at' => $event->created_at,
        ];
    }

    private function supportTicketRow(SupportTicket $ticket): array
    {
        return [
            'id' => $ticket->id,
            'user_id' => $ticket->user_id,
            'user' => $ticket->user?->profile?->first_name ?? $ticket->user?->name,
            'email' => $ticket->user?->email,
            'subject' => $ticket->subject,
            'category' => $ticket->category,
            'priority' => $ticket->priority,
            'status' => $ticket->status,
            'message' => $ticket->message,
            'attachment_url' => $ticket->attachment_url,
            'admin_note' => $ticket->admin_note,
            'resolved_at' => $ticket->resolved_at,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,
        ];
    }

    private function validateEvent(Request $request, bool $partial = false): array
    {
        $prefix = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$prefix, 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:3000'],
            'category' => ['sometimes', 'string', 'max:60'],
            'status' => ['sometimes', Rule::in(['draft', 'open', 'waitlist', 'full', 'closed', 'cancelled'])],
            'venue' => ['nullable', 'string', 'max:160'],
            'city' => ['nullable', 'string', 'max:120'],
            'starts_at' => ['nullable', 'date'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'cover_url' => ['nullable', 'url', 'max:2048'],
            'cover_image' => ['sometimes', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'images' => ['sometimes', 'array', 'max:8'],
            'images.*' => ['image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'is_premium' => ['sometimes', 'boolean'],
        ]);
    }

    private function syncEventImages(Request $request, Event $event, CloudinaryService $cloudinary): void
    {
        if ($request->hasFile('cover_image')) {
            $upload = $cloudinary->uploadEventImage($request->file('cover_image'));
            $event->images()->update(['is_cover' => false]);
            EventImage::query()->create([
                'event_id' => $event->id,
                'cloudinary_public_id' => $upload['public_id'] ?? null,
                'url' => $upload['url'],
                'sort_order' => 0,
                'is_cover' => true,
            ]);
            $event->update(['cover_url' => $upload['url']]);
        }

        $baseSort = $event->images()->max('sort_order') ?? 0;
        foreach ($request->file('images', []) as $index => $file) {
            $upload = $cloudinary->uploadEventImage($file);
            EventImage::query()->create([
                'event_id' => $event->id,
                'cloudinary_public_id' => $upload['public_id'] ?? null,
                'url' => $upload['url'],
                'sort_order' => $baseSort + $index + 1,
                'is_cover' => false,
            ]);
        }
    }

    private function countsBy(Builder $query, string $column): array
    {
        return $query->select($column, DB::raw('count(*) as total'))->groupBy($column)->get()
            ->map(fn ($item) => ['label' => $item->{$column} ?? 'non_renseigne', 'value' => (int) $item->total])
            ->values()
            ->all();
    }

    private function dailySeries(Builder $query, string $column, int $days = 14): array
    {
        $start = now()->subDays($days - 1)->startOfDay();
        $rows = $query->where($column, '>=', $start)->get([$column])
            ->groupBy(fn ($item) => Carbon::parse($item->{$column})->toDateString())
            ->map->count();

        return collect(range(0, $days - 1))->map(function (int $offset) use ($start, $rows) {
            $date = $start->copy()->addDays($offset)->toDateString();

            return ['date' => $date, 'value' => (int) ($rows[$date] ?? 0)];
        })->all();
    }

    private function authorizeAdmin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403, 'Acces administrateur requis.');

        return $user;
    }

    private function databaseStatus(): string
    {
        try {
            DB::select('select 1');

            return 'ok';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function audit(User $actor, string $action, mixed $target, ?string $reason = null, array $metadata = []): void
    {
        DB::table('audit_logs')->insert([
            'actor_id' => $actor->id,
            'action' => $action,
            'target_type' => is_object($target) ? $target::class : null,
            'target_id' => is_object($target) && method_exists($target, 'getKey') ? $target->getKey() : null,
            'reason' => $reason,
            'ip_address' => request()->ip(),
            'metadata' => json_encode($metadata),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
