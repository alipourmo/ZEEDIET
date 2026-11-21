{{-- Specialist sections of the client file (S4-T4): follow-up, notes, measurement history and chart. --}}
@php($summaryRows = array_values(array_filter($file['summary']['rows'], fn (array $row): bool => $row['first'] !== null)))

<section class="card" aria-labelledby="followup-title">
    <div class="card__body">
        <h2 id="followup-title" class="card__title">{{ __('followups.current_title') }}</h2>
        @if ($file['followUpOn'])
            <p>{{ __('followups.next_on', ['date' => \App\Support\Display::jdate($file['followUpOn'])]) }}</p>
        @else
            <p class="muted">{{ __('followups.none_set') }}</p>
        @endif
    </div>
</section>

<section class="card" id="notes" aria-labelledby="notes-title">
    <div class="card__body">
        <h2 id="notes-title" class="card__title">{{ __('notes.title') }}</h2>

        @if ($canManageNotes)
            <form method="POST" action="{{ route('specialist.notes.store', $client) }}" class="form" novalidate>
                @csrf
                <div class="form__grid">
                    <div class="field field--wide">
                        <label for="note-body">{{ __('notes.body') }}</label>
                        <textarea id="note-body" name="body" rows="3" maxlength="{{ \App\Http\Requests\Specialist\StoreSpecialistNoteRequest::MAX_BODY }}" required
                                  @error('body') aria-invalid="true" @enderror>{{ old('body') }}</textarea>
                        @error('body')<p class="field__error">{{ $message }}</p>@enderror
                    </div>

                    <div class="field">
                        <label for="next_follow_up_on">{{ __('notes.next_follow_up') }}</label>
                        <input id="next_follow_up_on" name="next_follow_up_on" type="text" inputmode="numeric"
                               value="{{ old('next_follow_up_on', $file['followUpOn'] ? \App\Support\Display::jdate($file['followUpOn']) : '') }}"
                               aria-describedby="follow-hint" @error('next_follow_up_on') aria-invalid="true" @enderror>
                        <p class="muted" id="follow-hint">{{ __('notes.follow_up_hint') }}</p>
                        @error('next_follow_up_on')<p class="field__error">{{ $message }}</p>@enderror
                    </div>
                </div>

                <p class="form__actions">
                    <button type="submit" class="btn btn--primary operator-button">{{ __('notes.save') }}</button>
                </p>
            </form>
        @endif

        @forelse ($file['notes'] as $note)
            <article class="note">
                <p class="muted">
                    {{ $note->specialist->name }}
                    · {{ \App\Support\Display::jdatetime($note->created_at) }}
                    @if ($note->next_follow_up_on)
                        · {{ __('notes.follow_up_label', ['date' => \App\Support\Display::jdate($note->next_follow_up_on)]) }}
                    @endif
                </p>
                <p class="note__body">{{ $note->body }}</p>
            </article>
        @empty
            <p class="muted">{{ __('notes.empty') }}</p>
        @endforelse
    </div>
</section>

<section class="card" aria-labelledby="measure-title">
    <div class="card__body">
        <h2 id="measure-title" class="card__title">{{ __('progress.history_title') }}</h2>

        <x-chart-line :chart="$file['chart']" :label="__('progress.history_title')" />

        @if ($summaryRows !== [])
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th scope="col">{{ __('reviews.col_measure') }}</th>
                            <th scope="col">{{ __('progress.col_first') }}</th>
                            <th scope="col">{{ __('progress.col_latest') }}</th>
                            <th scope="col">{{ __('reviews.col_change') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($summaryRows as $row)
                            <tr>
                                <th scope="row">{{ $row['label'] }} ({{ $row['unit'] }})</th>
                                <td>{{ \App\Support\Digits::toPersian($row['first']) }}</td>
                                <td>{{ \App\Support\Digits::toPersian($row['latest']) }}</td>
                                <td dir="ltr">{{ $row['delta'] !== null ? \App\Support\Digits::toPersian($row['delta']) : __('reviews.change_none') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <ul class="history">
            @forelse ($file['history'] as $entry)
                <li>
                    <strong>{{ $entry['date'] }}</strong>
                    @foreach ($entry['values'] as $item)
                        <span>{{ $item['label'] }} {{ \App\Support\Digits::toPersian($item['value']) }} {{ $item['unit'] }}</span>@if (! $loop->last) · @endif
                    @endforeach
                </li>
            @empty
                <li class="muted">{{ __('progress.history_empty') }}</li>
            @endforelse
        </ul>
    </div>
</section>
