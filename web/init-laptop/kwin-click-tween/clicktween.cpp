// SPDX-License-Identifier: GPL-2.0-or-later
#include <effect/effect.h>
#include <effect/effecthandler.h>
#include <input.h>
#include <input_event.h>
#include <input_event_spy.h>
#include <scene/item.h>
#include <scene/workspacescene.h>

#include "settings.h"
#include <QPointer>
#include <QVariantAnimation>

namespace KWin {

class ClickTweenEffect final : public Effect, public InputEventSpy
{
    Q_OBJECT
public:
    ClickTweenEffect()
        : m_cursor(effects->scene()->cursorItem())
        , m_original(m_cursor->transform())
    {
        reconfigure(ReconfigureAll);
        connect(&m_animation, &QVariantAnimation::valueChanged, this, [this](const QVariant &value) {
            m_scale = value.toReal();
            if (m_cursor) {
                // CursorItem's origin is the hotspot, not the image corner.
                m_cursor->setTransform(QTransform::fromScale(m_scale, m_scale) * m_original);
            }
        });
        connect(effects, &EffectsHandler::screenLockingChanged, this, [this](bool) { reset(); });
        input()->installInputEventSpy(this);
    }

    ~ClickTweenEffect() override { reset(); }

    void reconfigure(ReconfigureFlags) override
    {
        m_settings.read(KConfigGroup(effects->config(), QStringLiteral("Effect-clicktween")));
    }

    bool isActive() const override { return m_scale != 1.0; }

    void pointerButton(PointerButtonEvent *event) override
    {
        updateButtons(event->buttons);
    }

    void pointerMotion(PointerMotionEvent *event) override
    {
        // Reconcile state after device removal or other changes between clicks.
        updateButtons(event->buttons);
    }

private:
    void updateButtons(Qt::MouseButtons buttons)
    {
        if (effects->isScreenLocked()) {
            reset();
            return;
        }
        const bool pressed = bool(buttons & (Qt::LeftButton | Qt::RightButton));
        if (pressed == m_pressed) {
            return;
        }
        m_pressed = pressed;
        m_animation.stop();
        m_animation.setStartValue(m_scale);
        m_animation.setEndValue(pressed ? 0.8 : 1.0);
        m_animation.setDuration(pressed ? m_settings.clickTime : m_settings.releaseTime);
        m_animation.setEasingCurve(ClickTween::curveType(pressed ? m_settings.clickEasing : m_settings.releaseEasing));
        m_animation.start();
    }

    void reset()
    {
        m_animation.stop();
        m_pressed = false;
        m_scale = 1.0;
        if (m_cursor) {
            m_cursor->setTransform(m_original);
        }
    }

    QPointer<Item> m_cursor;
    QTransform m_original;
    QVariantAnimation m_animation;
    ClickTween::Settings m_settings;
    qreal m_scale = 1.0;
    bool m_pressed = false;
};

KWIN_EFFECT_FACTORY_SUPPORTED(ClickTweenEffect, "metadata.json",
    return effects->scene() && effects->scene()->cursorItem();)

} // namespace KWin

#include "clicktween.moc"
