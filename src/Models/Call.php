<?php

namespace RTippin\Messenger\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RTippin\Messenger\Contracts\MessengerProvider;
use RTippin\Messenger\Facades\Messenger;
use RTippin\Messenger\Support\Definitions;
use RTippin\Messenger\Traits\ScopesProvider;
use RTippin\Messenger\Traits\Uuids;

/**
 * App\Models\Messages\Call.
 *
 * @property string $id
 * @property string $thread_id
 * @property string $owner_id
 * @property string $owner_type
 * @property int $type
 * @property int $mode
 * @property int|null $room_id
 * @property string|null $room_pin
 * @property string|null $room_secret
 * @property string|null $call_ended
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\RTippin\Messenger\Models\CallParticipant[] $participants
 * @property-read int|null $participants_count
 * @property-read \RTippin\Messenger\Models\Thread $thread
 * @mixin Model|\Eloquent
 * @property-read Model|MessengerProvider $owner
 * @method static Builder|Call videoCall()
 * @method static Builder|Call active()
 * @method static Builder|Call hasProvider(string $relation, MessengerProvider $provider)
 * @method static Builder|Call forProvider(MessengerProvider $provider)
 * @method static Builder|Call notProvider(MessengerProvider $provider)
 * @property string|null $payload
 * @property int $setup_complete
 * @property int $teardown_complete
 */
class Call extends Model
{
    use Uuids;
    use ScopesProvider;

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'calls';

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var string
     */
    public $keyType = 'string';

    /**
     * The attributes that can be set with Mass Assignment.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * @var array
     */
    protected $casts = [
        'room_id' => 'integer',
        'setup_complete' => 'boolean',
        'teardown_complete' => 'boolean',
        'type' => 'integer',
    ];

    /**
     * @var null|CallParticipant
     */
    private ?CallParticipant $currentParticipantCache = null;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['call_ended'];

    /**
     * @return MorphTo|MessengerProvider
     */
    public function owner()
    {
        return $this->morphTo()->withDefault(function () {
            return Messenger::getGhostProvider();
        });
    }

    /**
     * @return BelongsTo|Thread
     */
    public function thread()
    {
        return $this->belongsTo(Thread::class);
    }

    /**
     * @return HasMany|CallParticipant|Collection
     */
    public function participants()
    {
        return $this->hasMany(
            CallParticipant::class,
            'call_id'
        );
    }

    /**
     * @return string
     */
    public function getTypeVerbose(): string
    {
        return Definitions::Call[$this->type];
    }

    /**
     * Scope a query for only video calls.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeVideoCall(Builder $query): Builder
    {
        return $query->where('type', '=', 1);
    }

    /**
     * Scope a query for only video calls.
     *
     * @param Builder $query
     * @return Builder
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('call_ended');
    }

    /**
     * @return bool
     */
    public function isActive(): bool
    {
        return is_null($this->call_ended);
    }

    /**
     * @return bool
     */
    public function isSetup(): bool
    {
        return $this->setup_complete;
    }

    /**
     * @return bool
     */
    public function isTornDown(): bool
    {
        return $this->teardown_complete;
    }

    /**
     * @return bool
     */
    public function hasEnded(): bool
    {
        return ! is_null($this->call_ended);
    }

    /**
     * @return bool
     */
    public function isVideoCall(): bool
    {
        return $this->type === 1;
    }

    /**
     * @param Thread|null $thread
     * @return bool
     */
    public function isGroupCall(Thread $thread = null): bool
    {
        return $thread
            ? $thread->isGroup()
            : $this->thread->isGroup();
    }

    /**
     * @param Thread|null $thread
     * @return string|null
     */
    public function name(Thread $thread = null): ?string
    {
        return $thread
            ? $thread->name()
            : $this->thread->name();
    }

    /**
     * @return CallParticipant|mixed|null
     */
    public function currentCallParticipant(): ?CallParticipant
    {
        if (! Messenger::isProviderSet()
            || $this->currentParticipantCache) {
            return $this->currentParticipantCache;
        }

        return $this->currentParticipantCache = $this->participants
            ->where('owner_id', Messenger::getProviderId())
            ->where('owner_type', '=', Messenger::getProviderClass())
            ->first();
    }

    /**
     * @param Thread|null $thread
     * @return bool
     */
    public function isCallAdmin(Thread $thread = null): bool
    {
        if ($this->hasEnded()
            || ! $this->currentCallParticipant()) {
            return false;
        }

        if ((string) Messenger::getProviderId() === (string) $this->owner_id
            && Messenger::getProviderClass() === $this->owner_type) {
            return true;
        }

        return $thread
                ? $thread->isAdmin()
                : $this->thread->isAdmin();
    }

    /**
     * @return bool
     */
    public function hasJoinedCall(): bool
    {
        return $this->currentCallParticipant()
            ? true
            : false;
    }

    /**
     * @return bool
     */
    public function wasKicked(): bool
    {
        return $this->currentCallParticipant()
            && $this->currentCallParticipant()->kicked;
    }

    /**
     * @return bool
     */
    public function isInCall(): bool
    {
        if ($this->hasEnded()
            || ! $this->currentCallParticipant()) {
            return false;
        }

        return is_null($this->currentCallParticipant()->left_call);
    }

    /**
     * @return bool
     */
    public function hasLeftCall(): bool
    {
        if ($this->hasEnded()
            || ! $this->currentCallParticipant()) {
            return false;
        }

        return ! is_null($this->currentCallParticipant()->left_call);
    }
}
